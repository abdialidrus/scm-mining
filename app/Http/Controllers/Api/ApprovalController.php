<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Approval;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ApprovalController extends Controller
{
    /**
     * Get pending approvals for the authenticated user
     */
    public function myPendingApprovals(Request $request): JsonResponse
    {
        $user = $request->user();

        // Get user's roles
        $userRoles = $user->getRoleNames();

        // Query approvals that are pending and assigned to user's roles
        $query = Approval::query()
            ->with([
                'approvable',
                'step',
                'approver',
                'approvedBy',
                'rejectedBy',
            ])
            ->where('status', Approval::STATUS_PENDING)
            ->where(function ($q) use ($user, $userRoles) {
                // Direct assignment (assigned_to_user_id)
                $q->where('assigned_to_user_id', $user->id)
                    // Or role-based assignment
                    ->orWhereIn('assigned_to_role', $userRoles);
            })
            ->orderBy('created_at', 'asc');

        // Filter by document type if provided
        if ($request->has('document_type')) {
            $docType = $request->query('document_type');
            if ($docType === 'purchase_request') {
                $query->where('approvable_type', 'App\\Models\\PurchaseRequest');
            } elseif ($docType === 'purchase_order') {
                $query->where('approvable_type', 'App\\Models\\PurchaseOrder');
            }
        }

        // Search by document number
        if ($request->has('search') && $search = trim($request->query('search'))) {
            $query->whereHas('approvable', function ($q) use ($search) {
                $q->where(DB::raw('LOWER(pr_number)'), 'like', '%' . strtolower($search) . '%')
                    ->orWhere(DB::raw('LOWER(po_number)'), 'like', '%' . strtolower($search) . '%');
            });
        }

        $perPage = (int) $request->query('per_page', 15);
        $approvals = $query->paginate($perPage);

        // Transform the response to include document details
        $transformed = $approvals->through(function ($approval) {
            $approvable = $approval->approvable;

            $documentNumber = null;
            $documentType = null;
            $documentUrl = null;
            $documentAmount = null;
            $submittedAt = null;
            $submitter = null;

            if ($approvable instanceof \App\Models\PurchaseRequest) {
                $documentNumber = $approvable->pr_number;
                $documentType = 'Purchase Request';
                $documentUrl = '/purchase-requests/' . $approvable->id;
                $documentAmount = null; // PRs don't have amounts
                $submittedAt = $approvable->submitted_at;
                $submitter = $approvable->requester;
            } elseif ($approvable instanceof \App\Models\PurchaseOrder) {
                $documentNumber = $approvable->po_number;
                $documentType = 'Purchase Order';
                $documentUrl = '/purchase-orders/' . $approvable->id;
                $documentAmount = $approvable->total_amount;
                $submittedAt = $approvable->submitted_at;
                $submitter = $approvable->submittedBy;
            }

            return [
                'id' => $approval->id,
                'status' => $approval->status,
                'comments' => $approval->comments,
                'created_at' => $approval->created_at?->toISOString(),
                'step' => $approval->step ? [
                    'id' => $approval->step->id,
                    'step_name' => $approval->step->step_name,
                    'sequence' => $approval->step->sequence,
                ] : null,
                'assigned_to_role' => $approval->assigned_to_role,
                'assigned_to_user_id' => $approval->assigned_to_user_id,
                'document' => [
                    'type' => $documentType,
                    'number' => $documentNumber,
                    'url' => $documentUrl,
                    'amount' => $documentAmount,
                    'submitted_at' => $submittedAt?->toISOString(),
                    'submitter' => $submitter ? [
                        'id' => $submitter->id,
                        'name' => $submitter->name,
                        'email' => $submitter->email,
                    ] : null,
                ],
            ];
        });

        return response()->json([
            'data' => $transformed,
        ]);
    }

    /**
     * Get approval statistics for the authenticated user
     */
    public function statistics(Request $request): JsonResponse
    {
        $user = $request->user();
        $userRoles = $user->getRoleNames();

        $pendingCount = Approval::query()
            ->where('status', Approval::STATUS_PENDING)
            ->where(function ($q) use ($user, $userRoles) {
                $q->where('assigned_to_user_id', $user->id)
                    ->orWhereIn('assigned_to_role', $userRoles);
            })
            ->count();

        $approvedCount = Approval::query()
            ->where('status', Approval::STATUS_APPROVED)
            ->where('approved_by_user_id', $user->id)
            ->whereDate('approved_at', '>=', now()->subDays(30))
            ->count();

        $rejectedCount = Approval::query()
            ->where('status', Approval::STATUS_REJECTED)
            ->where('rejected_by_user_id', $user->id)
            ->whereDate('rejected_at', '>=', now()->subDays(30))
            ->count();

        // Average approval time (in hours) for last 30 days
        $avgApprovalTime = Approval::query()
            ->where('approved_by_user_id', $user->id)
            ->where('status', Approval::STATUS_APPROVED)
            ->whereDate('approved_at', '>=', now()->subDays(30))
            ->whereNotNull('approved_at')
            ->get()
            ->map(function ($approval) {
                return $approval->created_at->diffInHours($approval->approved_at);
            })
            ->avg();

        return response()->json([
            'data' => [
                'pending_count' => $pendingCount,
                'approved_last_30_days' => $approvedCount,
                'rejected_last_30_days' => $rejectedCount,
                'average_approval_time_hours' => $avgApprovalTime ? round($avgApprovalTime, 1) : null,
            ],
        ]);
    }
}
