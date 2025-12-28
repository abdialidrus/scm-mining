<?php

use App\Http\Controllers\ApprovalWorkflowPageController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth'])->prefix('approval-workflows')->group(function () {
    Route::get('/', [ApprovalWorkflowPageController::class, 'index'])->name('approval-workflows.index');
    Route::get('/create', [ApprovalWorkflowPageController::class, 'create'])->name('approval-workflows.create');
    Route::get('/{approvalWorkflow}/edit', [ApprovalWorkflowPageController::class, 'edit'])->name('approval-workflows.edit');
});
