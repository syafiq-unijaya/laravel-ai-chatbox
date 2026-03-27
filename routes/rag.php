<?php

use Illuminate\Support\Facades\Route;
use SyafiqUnijaya\AiChatbox\Http\Controllers\RagController;

Route::get('/', [RagController::class, 'index'])->name('ai-chatbox.rag.index');
Route::post('/', [RagController::class, 'store'])->name('ai-chatbox.rag.store');
Route::delete('/{id}', [RagController::class, 'destroy'])->name('ai-chatbox.rag.destroy');
Route::post('/{id}/reprocess', [RagController::class, 'reprocess'])->name('ai-chatbox.rag.reprocess');
