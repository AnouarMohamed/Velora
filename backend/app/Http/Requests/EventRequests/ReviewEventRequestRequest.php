<?php

namespace App\Http\Requests\EventRequests;

use App\Models\EventRequest;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Form request for administrators to review and decide on an event request.
 *
 * Admins can either approve or reject a client's proposal.
 */
class ReviewEventRequestRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * Only administrators are authorized to review and decide on event requests.
     */
    public function authorize(): bool
    {
        $user = $this->user();

        return $user instanceof User && $user->isAdmin();
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * Rules:
     * - decision: Required, must be 'approved' or 'rejected'.
     * - rejection_reason: Required only if the decision is 'rejected'.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'decision' => ['required', Rule::in([
                EventRequest::STATUS_APPROVED,
                EventRequest::STATUS_REJECTED,
            ])],
            'rejection_reason' => ['required_if:decision,'.EventRequest::STATUS_REJECTED, 'nullable', 'string'],
        ];
    }
}
