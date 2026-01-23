from typing import List, Optional

from fastapi import FastAPI, HTTPException
from pydantic import BaseModel, Field
import numpy as np
from sklearn.cluster import KMeans
from sklearn.metrics import silhouette_score
from sklearn.preprocessing import StandardScaler

app = FastAPI(title="NexLoyal AI Service")


class TrainRequest(BaseModel):
    features: List[List[float]] = Field(default_factory=list)
    feature_keys: Optional[List[str]] = None
    min_k: int = 2
    max_k: int = 6


@app.post("/ai/cluster/train")
def train_clusters(payload: TrainRequest):
    if not payload.features:
        raise HTTPException(status_code=422, detail="No features provided.")

    data = np.array(payload.features, dtype=float)
    if data.ndim != 2 or data.shape[0] < 2:
        raise HTTPException(status_code=422, detail="Not enough samples for clustering.")

    n_samples = data.shape[0]
    min_k = max(2, int(payload.min_k))
    max_k = max(min_k, int(payload.max_k))
    max_k = min(max_k, n_samples - 1)

    scaler = StandardScaler()
    scaled = scaler.fit_transform(data)

    best_k = None
    best_score = None
    best_labels = None
    best_centroids = None

    for k in range(min_k, max_k + 1):
        model = KMeans(n_clusters=k, n_init=10, random_state=42)
        labels = model.fit_predict(scaled)
        score = silhouette_score(scaled, labels)
        if best_score is None or score > best_score:
            best_score = score
            best_k = k
            best_labels = labels
            best_centroids = model.cluster_centers_

    if best_labels is None:
        raise HTTPException(status_code=500, detail="Unable to train clusters.")

    return {
        "k": best_k,
        "silhouette": float(best_score) if best_score is not None else None,
        "labels": best_labels.tolist(),
        "centroids": best_centroids.tolist() if best_centroids is not None else None,
    }
