# Typing helpers used throughout request/response shapes.
from typing import Any, Dict, List, Optional

# FastAPI primitives for routing and error handling.
from fastapi import FastAPI, HTTPException, Request
# Pydantic models define and validate incoming request payloads.
from pydantic import BaseModel, Field, root_validator
import hashlib
import json
import os
from datetime import datetime, timezone

# Numerical processing and ML utilities.
import numpy as np
# K-Means clustering and quality metrics.
from sklearn.cluster import KMeans
from sklearn.metrics import silhouette_score
from sklearn.preprocessing import StandardScaler

# FastAPI application instance.
app = FastAPI(title="NexLoyal AI Service")

# Configuration and feature flags.
# These values can be overridden via environment variables at deploy time.
MODEL_STATE_PATH = os.getenv("AI_MODEL_STATE_PATH", "model_state.json")
MAX_K_LIMIT = int(os.getenv("AI_MAX_K", "20"))
REQUIRE_API_KEY = True
API_KEY = os.getenv("AI_API_KEY", "").strip()
CODE_VERSION = os.getenv("AI_CODE_VERSION", "").strip() or "unknown"

# In-memory cache of the most recently trained model state.
_model_state: Optional[Dict[str, Any]] = None


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


@app.post("/ai/cluster/train")
def train_clusters(payload: TrainRequest, request: Request):
    # Train a K-Means model, select best k, and persist model state.
    _validate_api_key(request)

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
    if min_k < 2:
        _raise_validation("invalid_k_range", "min_k must be at least 2.", details={"min_k": min_k})
    if max_k <= min_k:
        _raise_validation("invalid_k_range", "max_k must be greater than min_k.", details={"min_k": min_k, "max_k": max_k})

    # Enforce hard upper bound so we don't request more clusters than samples.
    max_allowed = min(MAX_K_LIMIT, n_samples - 1)
    if max_k > max_allowed:
        _raise_validation(
            "k_too_large",
            "max_k is too large for the dataset.",
            details={"max_k": max_k, "max_allowed": max_allowed},
            hint="Lower max_k or provide more samples.",
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

    # ---------------------------
    # Step 3: Train multiple K values
    # ---------------------------
    # Search for the best k using silhouette score.
    best_k = None
    best_score = None
    best_inertia = None
    best_labels = None
    best_centroids = None
    silhouette_scores: List[Dict[str, Any]] = []
    inertia_scores: List[Dict[str, Any]] = []

    # Evaluate each k and keep the best scoring result.
    for k in range(min_k, max_k + 1):
        # Fit K-Means for the current number of clusters.
        model = KMeans(n_clusters=k, n_init=10, random_state=42)
        labels = model.fit_predict(scaled)
        # Inertia is how tight the clusters are (lower is better).
        inertia = float(model.inertia_)
        inertia_scores.append({"k": k, "inertia": inertia})

        # Silhouette score only works when there is more than one cluster label.
        score = None
        if len(set(labels)) > 1:
            score = float(silhouette_score(scaled, labels))
        silhouette_scores.append({"k": k, "score": score})

        # Track the best scoring model so far.
        if score is not None and (best_score is None or score > best_score):
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
    }


@app.post("/ai/cluster/predict")
def predict_cluster(payload: PredictRequest, request: Request):
    # Predict the closest cluster for a single feature vector.
    _validate_api_key(request)

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
