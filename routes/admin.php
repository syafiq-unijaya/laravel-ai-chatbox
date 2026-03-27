<?php

use Illuminate\Support\Facades\Route;
use SyafiqUnijaya\AiChatbox\Http\Controllers\AdminController;

Route::get('/', [AdminController::class, 'index'])->name('ai-chatbox.admin.index');
Route::get('/conversations', [AdminController::class, 'conversations'])->name('ai-chatbox.admin.conversations');
Route::get('/conversations/data', [AdminController::class, 'conversationsData'])->name('ai-chatbox.admin.conversations.data');
Route::get('/conversations/{id}/messages', [AdminController::class, 'conversationMessages'])->name('ai-chatbox.admin.conversations.messages');
