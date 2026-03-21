# Typing helpers used throughout request/response shapes.
from pathlib import Path
from typing import Any, Dict, List, Optional
import gzip

# FastAPI primitives for routing and error handling.
from fastapi import FastAPI, HTTPException, Request
# Pydantic models define and validate incoming request payloads.
from pydantic import BaseModel, Field, ValidationError, root_validator
import hashlib
import json
import os
from datetime import datetime, timezone

# Numerical processing and ML utilities.
import numpy as np
# K-Means clustering and quality metrics.
from sklearn.cluster import KMeans, MiniBatchKMeans
from sklearn.decomposition import PCA
from sklearn.metrics import silhouette_score
from sklearn.preprocessing import StandardScaler

# FastAPI application instance.
app = FastAPI(title="NexLoyal AI Service")


@app.middleware("http")
async def _gunzip_requests(request: Request, call_next):
    # Accept gzipped JSON bodies so Laravel can send large training payloads more efficiently.
    if request.headers.get("content-encoding", "").lower() == "gzip":
        compressed_body = await request.body()
        decompressed_body = gzip.decompress(compressed_body)

        async def receive() -> Dict[str, Any]:
            return {"type": "http.request", "body": decompressed_body, "more_body": False}

        request = Request(request.scope, receive)

    return await call_next(request)


def _load_env_file(path: Path) -> None:
    # Load simple KEY=VALUE pairs into the process environment if missing.
    if not path.exists():
        return

    for raw_line in path.read_text(encoding="utf-8").splitlines():
        line = raw_line.strip()
        if not line or line.startswith("#") or "=" not in line:
            continue

        key, value = line.split("=", 1)
        key = key.strip()
        value = value.strip()
        if not key or key in os.environ:
            continue

        if len(value) >= 2 and value[0] == value[-1] and value[0] in ("'", '"'):
            value = value[1:-1]

        os.environ[key] = value


_SERVICE_DIR = Path(__file__).resolve().parent
_load_env_file(_SERVICE_DIR / ".env")
_load_env_file(_SERVICE_DIR.parent / ".env")

# Configuration and feature flags.
# These values can be overridden via environment variables at deploy time.
MODEL_STATE_PATH = os.getenv("AI_MODEL_STATE_PATH", "model_state.json")
MAX_K_LIMIT = int(os.getenv("AI_MAX_K", "20"))
SILHOUETTE_SAMPLE_SIZE = int(os.getenv("AI_SILHOUETTE_SAMPLE_SIZE", "5000"))
MINIBATCH_THRESHOLD = int(os.getenv("AI_MINIBATCH_THRESHOLD", "10000"))
MINIBATCH_SIZE = int(os.getenv("AI_MINIBATCH_BATCH_SIZE", "2048"))
KMEANS_MAX_ITER = int(os.getenv("AI_KMEANS_MAX_ITER", "100"))
AI_FIXED_K_RAW = os.getenv("AI_FIXED_K", "").strip()
REQUIRE_API_KEY = True
API_KEY = os.getenv("AI_API_KEY", "").strip()
CODE_VERSION = os.getenv("AI_CODE_VERSION", "").strip() or "unknown"

# In-memory cache of the most recently trained model state.
_model_state: Optional[Dict[str, Any]] = None


@app.get("/health")
def health() -> Dict[str, Any]:
    # Expose a lightweight health payload so Laravel can preflight the AI service.
    state = _ensure_model_state()
    return {
        "status": "ok",
        "model_loaded": state is not None,
        "code_version": CODE_VERSION,
    }


# Request payload for training the clustering model.
class TrainRequest(BaseModel):
    # Matrix of feature vectors, one row per customer.
    features: List[List[float]] = Field(default_factory=list)
    # Optional human-readable names for each feature column.
    feature_names: Optional[List[str]] = None
    # Optional alternative field name for feature_names.
    feature_keys: Optional[List[str]] = None
    # Minimum number of clusters to test.
    min_k: int = 2
    # Maximum number of clusters to test.
    max_k: int = 6
    # Optional caps for outliers keyed by feature name.
    outlier_caps: Optional[Dict[str, float]] = None
    # Optional list of feature names to log-transform.
    log_transforms: Optional[List[str]] = None
    # Optional schema version for tracing dataset evolution.
    feature_schema_version: Optional[int] = None
    # Optional algorithm version tag.
    algorithm_version: Optional[str] = None
    # Optional code version tag.
    code_version: Optional[str] = None
    # Optional training metadata from Laravel to support smart retraining decisions.
    training_metadata: Optional[Dict[str, Any]] = None
    # Optional fixed k override from Laravel; when set, skip k-range search.
    fixed_k: Optional[int] = None

    @root_validator(pre=True)
    def normalize_feature_names(cls, values: Dict[str, Any]) -> Dict[str, Any]:
        # Ensure feature_names is populated when only feature_keys is provided.
        feature_names = values.get("feature_names")
        feature_keys = values.get("feature_keys")
        if not feature_names and feature_keys:
            values["feature_names"] = feature_keys
        return values


class PredictRequest(BaseModel):
    # Feature vector to classify into a cluster.
    features: Optional[List[float]] = None
    # Reserved for future lookup support (not used in this service).
    customer_id: Optional[str] = None


class PredictBatchRequest(BaseModel):
    # Matrix of feature vectors, one row per customer.
    features: List[List[float]] = Field(default_factory=list)


@app.get("/ai/model/metadata")
def model_metadata(request: Request) -> Dict[str, Any]:
    # Expose persisted model metadata so Laravel can make retraining decisions.
    _validate_api_key(request)
    state = _ensure_model_state()
    return {
        "status": "ok",
        "model_loaded": state is not None,
        "model_metadata": (state or {}).get("model_metadata"),
        "selected_k": (state or {}).get("selected_k"),
    }


def _error_response(error_code: str, message: str, details: Any = None, hint: Optional[str] = None) -> Dict[str, Any]:
    # Build a consistent error payload for API responses.
    payload: Dict[str, Any] = {
        "error_code": error_code,
        "message": message,
        "details": details or {},
        "hint": hint,
    }
    return payload


def _raise_validation(error_code: str, message: str, details: Any = None, hint: Optional[str] = None, status: int = 422) -> None:
    # Raise a FastAPI HTTPException using the standard error payload.
    raise HTTPException(
        status_code=status,
        detail=_error_response(error_code, message, details=details, hint=hint),
    )


async def _parse_json_body(request: Request, model_cls: type[BaseModel]) -> BaseModel:
    # Parse JSON manually so malformed or oversized payloads return a specific error.
    raw_body = await request.body()
    if not raw_body:
        _raise_validation("body_empty", "Request body is empty.", status=400)

    try:
        decoded = json.loads(raw_body)
    except json.JSONDecodeError as exc:
        _raise_validation(
            "invalid_json",
            "Request body is not valid JSON.",
            details={
                "line": exc.lineno,
                "column": exc.colno,
                "message": exc.msg,
                "body_prefix": raw_body[:160].decode("utf-8", errors="replace"),
            },
            status=400,
        )

    try:
        return model_cls.model_validate(decoded)
    except ValidationError as exc:
        raise HTTPException(status_code=422, detail=exc.errors()) from exc


def _validate_api_key(request: Request) -> None:
    # Enforce API key checks unless disabled by configuration.
    if not REQUIRE_API_KEY:
        return
    if not API_KEY:
        raise HTTPException(
            status_code=500,
            detail=_error_response(
                "ai_service_misconfigured",
                "AI_API_KEY is not configured.",
                hint="Set AI_API_KEY in the AI service environment.",
            ),
        )
    provided = request.headers.get("X-AI-KEY", "")
    if not provided or provided != API_KEY:
        raise HTTPException(
            status_code=401,
            detail=_error_response(
                "invalid_api_key",
                "Invalid or missing AI API key.",
                hint="Provide the X-AI-KEY header.",
            ),
        )


def _load_model_state() -> Optional[Dict[str, Any]]:
    # Load the persisted model state from disk, if present.
    if not os.path.exists(MODEL_STATE_PATH):
        return None
    try:
        with open(MODEL_STATE_PATH, "r", encoding="utf-8") as handle:
            return json.load(handle)
    except Exception:
        return None


def _save_model_state(state: Dict[str, Any]) -> None:
    # Persist the model state to disk (best-effort).
    try:
        with open(MODEL_STATE_PATH, "w", encoding="utf-8") as handle:
            json.dump(state, handle)
    except Exception:
        # Persisting locally is best-effort; the response still returns the metadata.
        pass


def _ensure_model_state() -> Optional[Dict[str, Any]]:
    # Cache model state in memory to avoid reloading for every request.
    global _model_state
    if _model_state is None:
        _model_state = _load_model_state()
    return _model_state


@app.on_event("startup")
def _startup() -> None:
    # Prime the in-memory model cache on service startup.
    _ensure_model_state()


def _compute_dataset_hash(features: List[List[float]], feature_names: List[str]) -> str:
    # Generate a stable hash for the training dataset and schema.
    payload = json.dumps(
        {"features": features, "feature_names": feature_names},
        sort_keys=True,
        separators=(",", ":"),
    )
    return hashlib.sha256(payload.encode("utf-8")).hexdigest()


def _resolve_fixed_k(payload_fixed_k: Optional[int]) -> Optional[int]:
    # Resolve fixed k from request first, then from environment.
    if payload_fixed_k is not None:
        return int(payload_fixed_k)
    if AI_FIXED_K_RAW == "":
        return None
    try:
        return int(AI_FIXED_K_RAW)
    except ValueError:
        return None


def _apply_preprocessing(
    raw: np.ndarray,
    feature_names: List[str],
    outlier_caps: Optional[Dict[str, float]],
    log_transforms: Optional[List[str]],
) -> np.ndarray:
    # Apply caps and optional log transforms to each feature column.
    caps = outlier_caps or {}
    log_keys = set([name for name in (log_transforms or [])])
    processed = raw.astype(float).copy()
    for idx, name in enumerate(feature_names):
        column = processed[:, idx]
        cap = caps.get(name)
        if cap is not None:
            column = np.minimum(column, float(cap))
        if name in log_keys:
            column = np.log1p(np.maximum(column, 0))
        processed[:, idx] = column
    return processed


def _build_cluster_model(k: int, n_samples: int) -> KMeans | MiniBatchKMeans:
    # MiniBatchKMeans scales much better on large datasets with minimal quality tradeoff.
    if n_samples >= MINIBATCH_THRESHOLD:
        return MiniBatchKMeans(
            n_clusters=k,
            n_init=5,
            random_state=42,
            batch_size=MINIBATCH_SIZE,
            max_iter=KMEANS_MAX_ITER,
        )

    return KMeans(
        n_clusters=k,
        n_init=10,
        random_state=42,
        max_iter=KMEANS_MAX_ITER,
    )


def _score_silhouette(scaled: np.ndarray, labels: np.ndarray) -> Optional[float]:
    # Sampled silhouette scoring avoids the O(n^2) cost of evaluating every pair on large datasets.
    if len(set(labels.tolist())) <= 1:
        return None

    n_samples = int(scaled.shape[0])
    sample_size = min(SILHOUETTE_SAMPLE_SIZE, n_samples)

    return float(
        silhouette_score(
            scaled,
            labels,
            sample_size=sample_size if sample_size < n_samples else None,
            random_state=42,
        )
    )


def _fit_projection(scaled: np.ndarray) -> Optional[Dict[str, Any]]:
    # Fit a lightweight 2D PCA projection for visualization without changing the clustering model.
    if scaled.ndim != 2 or scaled.shape[0] < 2 or scaled.shape[1] < 2:
        return None

    projector = PCA(n_components=2)
    points = projector.fit_transform(scaled)

    return {
        "method": "pca_2d",
        "points": points.tolist(),
        "mean": projector.mean_.tolist(),
        "components": projector.components_.tolist(),
        "explained_variance_ratio": projector.explained_variance_ratio_.tolist(),
    }


def _project_with_state(scaled: np.ndarray, state: Dict[str, Any]) -> Optional[Dict[str, Any]]:
    # Reuse the saved PCA parameters so delta predictions land in the same 2D space as the latest model.
    projection_state = state.get("projection") or {}
    method = projection_state.get("method")
    components = projection_state.get("components")
    mean = projection_state.get("mean")

    if method != "pca_2d" or not isinstance(components, list) or not isinstance(mean, list):
        return None

    component_matrix = np.array(components, dtype=float)
    mean_vector = np.array(mean, dtype=float)
    if component_matrix.ndim != 2 or component_matrix.shape[0] != 2:
        return None
    if mean_vector.ndim != 1 or mean_vector.shape[0] != scaled.shape[1]:
        return None

    projected = (scaled - mean_vector) @ component_matrix.T

    return {
        "method": "pca_2d",
        "points": projected.tolist(),
        "explained_variance_ratio": projection_state.get("explained_variance_ratio") or [],
    }


@app.post("/ai/cluster/train")
async def train_clusters(request: Request):
    # Train a K-Means model, select best k, and persist model state.
    _validate_api_key(request)
    payload = await _parse_json_body(request, TrainRequest)

    # ---------------------------
    # Step 1: Validate input data
    # ---------------------------
    # Basic payload sanity checks.
    if not payload.features:
        _raise_validation("features_empty", "No features provided.", hint="Send a non-empty features matrix.")

    data = np.array(payload.features, dtype=float)
    # Expect a 2D matrix with at least two rows so clustering can work.
    if data.ndim != 2 or data.shape[0] < 2:
        _raise_validation("not_enough_samples", "Not enough samples for clustering.", details={"n_rows": int(data.shape[0])})

    # Reject NaN/Infinity values to avoid invalid math in preprocessing/clustering.
    if not np.isfinite(data).all():
        _raise_validation(
            "non_finite_values",
            "Features contain NaN or infinite values.",
            hint="Clean the dataset to remove NaN/inf before training.",
        )

    # Validate feature naming against the number of columns in the matrix.
    feature_names = payload.feature_names or []
    if feature_names and data.shape[1] != len(feature_names):
        _raise_validation(
            "feature_length_mismatch",
            "Feature vector length does not match feature_names.",
            details={"expected": len(feature_names), "received": int(data.shape[1])},
        )
    if not feature_names:
        # Generate default names when none are provided.
        feature_names = [f"f{i}" for i in range(int(data.shape[1]))]

    # Validate and normalize the k range.
    n_samples = int(data.shape[0])
    min_k = int(payload.min_k)
    max_k = int(payload.max_k)
    fixed_k = _resolve_fixed_k(payload.fixed_k)

    if fixed_k is None:
        if min_k < 2:
            _raise_validation("invalid_k_range", "min_k must be at least 2.", details={"min_k": min_k})
        if max_k <= min_k:
            _raise_validation("invalid_k_range", "max_k must be greater than min_k.", details={"min_k": min_k, "max_k": max_k})
    elif fixed_k < 2:
        _raise_validation("invalid_fixed_k", "fixed_k must be at least 2.", details={"fixed_k": fixed_k})

    # Enforce hard upper bound so we don't request more clusters than samples.
    max_allowed = min(MAX_K_LIMIT, n_samples - 1)
    if fixed_k is None and max_k > max_allowed:
        _raise_validation(
            "k_too_large",
            "max_k is too large for the dataset.",
            details={"max_k": max_k, "max_allowed": max_allowed},
            hint="Lower max_k or provide more samples.",
        )
    if fixed_k is not None and fixed_k > max_allowed:
        _raise_validation(
            "fixed_k_too_large",
            "fixed_k is too large for the dataset.",
            details={"fixed_k": fixed_k, "max_allowed": max_allowed},
            hint="Lower fixed_k or provide more samples.",
        )

    # ---------------------------
    # Step 2: Preprocess features
    # ---------------------------
    started = datetime.now(timezone.utc)

    # Preprocess and standardize feature values before clustering.
    processed = _apply_preprocessing(data, feature_names, payload.outlier_caps, payload.log_transforms)

    # Standardization keeps each feature on the same scale.
    scaler = StandardScaler()
    scaled = scaler.fit_transform(processed)
    projection = _fit_projection(scaled)

    # ---------------------------
    # Step 3: Train model
    # ---------------------------
    # Search for the best k using silhouette score unless fixed k is provided.
    best_k = None
    best_score = None
    best_inertia = None
    best_labels = None
    best_centroids = None
    silhouette_scores: List[Dict[str, Any]] = []
    inertia_scores: List[Dict[str, Any]] = []

    # Evaluate each k and keep the best scoring result.
    used_minibatch = n_samples >= MINIBATCH_THRESHOLD

    if fixed_k is not None:
        # Fixed-k mode: train only once and skip k-search for predictable runtime.
        model = _build_cluster_model(fixed_k, n_samples)
        labels = model.fit_predict(scaled)
        best_k = fixed_k
        best_labels = labels
        best_centroids = model.cluster_centers_
        best_inertia = float(model.inertia_)
        best_score = _score_silhouette(scaled, labels)
        silhouette_scores.append({"k": fixed_k, "score": best_score})
        inertia_scores.append({"k": fixed_k, "inertia": best_inertia})
    else:
        for k in range(min_k, max_k + 1):
            # Fit a clustering model for the current number of clusters.
            model = _build_cluster_model(k, n_samples)
            labels = model.fit_predict(scaled)
            # Inertia is how tight the clusters are (lower is better).
            inertia = float(model.inertia_)
            inertia_scores.append({"k": k, "inertia": inertia})

            # Silhouette score only works when there is more than one cluster label.
            score = _score_silhouette(scaled, labels)
            silhouette_scores.append({"k": k, "score": score})

            # Track the best scoring model so far.
            # Silhouette remains the primary selector; inertia breaks near-ties toward tighter clusters.
            if score is not None and (
                best_score is None
                or score > best_score
                or (np.isclose(score, best_score) and (best_inertia is None or inertia < best_inertia))
            ):
                best_score = score
                best_k = k
                best_labels = labels
                best_centroids = model.cluster_centers_
                best_inertia = inertia

    # ---------------------------
    # Step 4: Save model snapshot
    # ---------------------------
    # Fail fast if no valid model could be selected.
    if best_labels is None or best_centroids is None or best_k is None:
        raise HTTPException(
            status_code=500,
            detail=_error_response(
                "training_failed",
                "Unable to train clusters.",
                hint="Check the input data and try again.",
            ),
        )

    finished = datetime.now(timezone.utc)
    duration_ms = int((finished - started).total_seconds() * 1000)

    # Prepare metadata about the trained model for traceability.
    model_metadata = {
        "dataset_hash": _compute_dataset_hash(payload.features, feature_names),
        "feature_schema_version": payload.feature_schema_version,
        "algorithm_version": payload.algorithm_version or "kmeans_v1",
        "code_version": payload.code_version or CODE_VERSION,
        "trained_at": finished.isoformat(),
    }
    if payload.training_metadata and isinstance(payload.training_metadata, dict):
        # Keep Laravel-provided watermarks in the model metadata for smart retraining checks.
        model_metadata.update(payload.training_metadata)

    # Store all parameters needed to reproduce scaling and predictions.
    model_state = {
        "centroids": best_centroids.tolist(),
        "scaler_mean": scaler.mean_.tolist(),
        "scaler_scale": scaler.scale_.tolist(),
        "feature_names": feature_names,
        "outlier_caps": payload.outlier_caps or {},
        "log_transforms": payload.log_transforms or [],
        "selected_k": best_k,
        "model_metadata": model_metadata,
        "projection": None
        if projection is None
        else {
            "method": projection["method"],
            "mean": projection["mean"],
            "components": projection["components"],
            "explained_variance_ratio": projection["explained_variance_ratio"],
        },
    }

    # Update in-memory cache and persist to disk.
    global _model_state
    _model_state = model_state
    _save_model_state(model_state)

    # ---------------------------
    # Step 5: Build API response
    # ---------------------------
    # Return clustering results and diagnostic metrics to the caller.
    return {
        "labels": best_labels.tolist(),
        "centroids": best_centroids.tolist(),
        "selected_k": best_k,
        "final_silhouette": best_score,
        "final_inertia": best_inertia,
        # Diagnostic series for charts and debugging.
        "silhouette_scores": silhouette_scores,
        "inertia_scores": inertia_scores,
        # Dataset shape and schema reference.
        "data_stats": {
            "n_rows": n_samples,
            "n_features": int(data.shape[1]),
            "feature_names": feature_names,
        },
        # Timing info for monitoring performance.
        "timing": {
            "started_at": started.isoformat(),
            "finished_at": finished.isoformat(),
            "duration_ms": duration_ms,
        },
        "training_strategy": {
            "estimator": "minibatch_kmeans" if used_minibatch else "kmeans",
            "mode": "fixed_k" if fixed_k is not None else "k_search",
            "fixed_k": fixed_k,
            "silhouette_sample_size": min(SILHOUETTE_SAMPLE_SIZE, n_samples),
            "minibatch_threshold": MINIBATCH_THRESHOLD,
        },
        # Scaler parameters included for transparency and reproducibility.
        "scaler": {
            "mean": scaler.mean_.tolist(),
            "scale": scaler.scale_.tolist(),
            "feature_names": feature_names,
            "outlier_caps": payload.outlier_caps or {},
            "log_transforms": payload.log_transforms or [],
        },
        # Metadata that ties the model to a dataset and version.
        "model_metadata": model_metadata,
        # 2D projection points for thesis/report visualizations.
        "projection": None
        if projection is None
        else {
            "method": projection["method"],
            "points": projection["points"],
            "explained_variance_ratio": projection["explained_variance_ratio"],
        },
    }


@app.post("/ai/cluster/predict")
async def predict_cluster(request: Request):
    # Predict the closest cluster for a single feature vector.
    _validate_api_key(request)
    payload = await _parse_json_body(request, PredictRequest)

    # ---------------------------
    # Step 1: Load model + validate input
    # ---------------------------
    # Ensure we have a trained model to use.
    state = _ensure_model_state()
    if not state:
        raise HTTPException(
            status_code=409,
            detail=_error_response(
                "model_not_trained",
                "No trained model is available.",
                hint="Train the model before calling /ai/cluster/predict.",
            ),
        )

    # This service does not fetch customer features by ID.
    if payload.features is None and payload.customer_id:
        _raise_validation(
            "customer_lookup_not_supported",
            "customer_id lookup is not supported in the AI service.",
            hint="Provide raw feature values in the request instead.",
            status=400,
        )

    # We require a numeric feature vector for prediction.
    if payload.features is None:
        _raise_validation("features_missing", "Features are required for prediction.", hint="Send a feature vector.")

    # Validate feature vector shape and length against the trained schema.
    feature_names = state.get("feature_names") or []
    features = np.array(payload.features, dtype=float)
    if features.ndim != 1:
        _raise_validation("invalid_features_shape", "Features must be a flat vector.")
    if feature_names and len(features) != len(feature_names):
        _raise_validation(
            "feature_length_mismatch",
            "Feature vector length does not match trained schema.",
            details={"expected": len(feature_names), "received": int(len(features))},
        )
    if not np.isfinite(features).all():
        _raise_validation("non_finite_values", "Features contain NaN or infinite values.")

    # ---------------------------
    # Step 2: Apply preprocessing + scaling
    # ---------------------------
    # Apply the same preprocessing used during training.
    processed = _apply_preprocessing(
        features.reshape(1, -1),
        feature_names or [f"f{i}" for i in range(int(features.shape[0]))],
        state.get("outlier_caps"),
        state.get("log_transforms"),
    )
    # Apply the stored scaler parameters (or leave raw if absent).
    mean = np.array(state.get("scaler_mean", []), dtype=float)
    scale = np.array(state.get("scaler_scale", []), dtype=float)
    if mean.size and scale.size:
        # Protect against divide-by-zero in case a feature had zero variance.
        scale = np.where(scale == 0, 1, scale)
        scaled = (processed - mean) / scale
    else:
        scaled = processed

    # ---------------------------
    # Step 3: Compute nearest cluster
    # ---------------------------
    # Compute distance to each centroid and return the closest cluster ID.
    centroids = np.array(state.get("centroids", []), dtype=float)
    if centroids.size == 0:
        raise HTTPException(
            status_code=500,
            detail=_error_response("model_state_invalid", "Centroids are missing from model state."),
        )

    distances = np.linalg.norm(centroids - scaled, axis=1)
    cluster_index = int(np.argmin(distances))

    # Return the closest cluster and metadata for traceability.
    return {
        "cluster_id": cluster_index,
        "distance": float(distances[cluster_index]),
        "selected_k": state.get("selected_k"),
        "model_metadata": state.get("model_metadata"),
    }


@app.post("/ai/cluster/predict-batch")
async def predict_batch(request: Request):
    # Predict labels for a full feature matrix using the currently saved model state.
    _validate_api_key(request)
    payload = await _parse_json_body(request, PredictBatchRequest)

    state = _ensure_model_state()
    if not state:
        raise HTTPException(
            status_code=409,
            detail=_error_response(
                "model_not_trained",
                "No trained model is available.",
                hint="Train the model before calling /ai/cluster/predict-batch.",
            ),
        )

    if not payload.features:
        _raise_validation("features_empty", "No features provided.", hint="Send a non-empty features matrix.")

    data = np.array(payload.features, dtype=float)
    if data.ndim != 2:
        _raise_validation("invalid_features_shape", "Features must be a 2D matrix.")
    if not np.isfinite(data).all():
        _raise_validation("non_finite_values", "Features contain NaN or infinite values.")

    feature_names = state.get("feature_names") or []
    if feature_names and data.shape[1] != len(feature_names):
        _raise_validation(
            "feature_length_mismatch",
            "Feature vector length does not match trained schema.",
            details={"expected": len(feature_names), "received": int(data.shape[1])},
        )
    if not feature_names:
        feature_names = [f"f{i}" for i in range(int(data.shape[1]))]

    processed = _apply_preprocessing(
        data,
        feature_names,
        state.get("outlier_caps"),
        state.get("log_transforms"),
    )

    mean = np.array(state.get("scaler_mean", []), dtype=float)
    scale = np.array(state.get("scaler_scale", []), dtype=float)
    if mean.size and scale.size:
        scale = np.where(scale == 0, 1, scale)
        scaled = (processed - mean) / scale
    else:
        scaled = processed

    centroids = np.array(state.get("centroids", []), dtype=float)
    if centroids.size == 0:
        raise HTTPException(
            status_code=500,
            detail=_error_response("model_state_invalid", "Centroids are missing from model state."),
        )

    distances = np.linalg.norm(centroids[None, :, :] - scaled[:, None, :], axis=2)
    labels = np.argmin(distances, axis=1)
    projection = _project_with_state(scaled, state)

    return {
        "labels": labels.tolist(),
        "centroids": centroids.tolist(),
        "selected_k": state.get("selected_k"),
        "model_metadata": state.get("model_metadata"),
        "projection": projection,
    }
