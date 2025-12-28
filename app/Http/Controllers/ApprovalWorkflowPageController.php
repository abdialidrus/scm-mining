<?php

namespace App\Http\Controllers;

use App\Models\ApprovalWorkflow;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ApprovalWorkflowPageController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', ApprovalWorkflow::class);

        return Inertia::render('approval-workflows/Index');
    }

    public function create(): Response
    {
        $this->authorize('create', ApprovalWorkflow::class);

        return Inertia::render('approval-workflows/Form', [
            'workflow' => null,
        ]);
    }

    public function edit(ApprovalWorkflow $approvalWorkflow): Response
    {
        $this->authorize('update', $approvalWorkflow);

        $approvalWorkflow->load('steps');

        return Inertia::render('approval-workflows/Form', [
            'workflow' => $approvalWorkflow,
        ]);
    }
}
