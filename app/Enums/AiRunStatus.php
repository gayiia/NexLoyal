<?php

// This enum defines the lifecycle states for AI clustering runs.
namespace App\Enums;

// This list is used for persistence and UI display of AI job status.
enum AiRunStatus: string
{
    // This indicates the run is created but has not started yet.
    case PENDING = 'pending';
    // This indicates the run is actively processing.
    case RUNNING = 'running';
    // This indicates the run finished successfully.
    case COMPLETED = 'completed';
    // This indicates the run failed due to an error.
    case FAILED = 'failed';
}
