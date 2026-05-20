<?php

namespace App\Services\Feedbacks;

use App\Models\Feedback;

/**
 * Data Transfer Object (DTO) representing the outcome of a feedback approval operation.
 *
 * This class encapsulates the updated Feedback model and a descriptive message
 * about the operation's result, facilitating clean communication between
 * the FeedbackService and its consumers (e.g., Controllers).
 */
readonly class FeedbackApprovalResult
{
    /**
     * @param  Feedback  $feedback  The updated feedback instance (approved or rejected).
     * @param  string  $message  A descriptive success or status message.
     */
    public function __construct(
        public Feedback $feedback,
        public string $message,
    ) {}
}
