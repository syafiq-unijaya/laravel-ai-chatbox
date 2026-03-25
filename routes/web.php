<?php

use Illuminate\Support\Facades\Route;
use SyafiqUnijaya\AiChatbox\Http\Controllers\ChatboxController;

Route::post('/message', [ChatboxController::class, 'sendMessage'])
    ->name('ai-chatbox.message');
