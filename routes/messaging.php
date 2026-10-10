<?php

use App\Http\Controllers\Api\V1\CourseMessagingController as Messaging;
use App\Http\Middleware\EnsureMessagingAccess;
use Illuminate\Support\Facades\Route;

Route::prefix('v1/messaging')->middleware(['auth:sanctum', EnsureMessagingAccess::class, 'throttle:240,1'])->name('messaging.')->group(function (): void {
    Route::get('courses', [Messaging::class, 'courses'])->name('courses');
    Route::get('courses/{course}/students', [Messaging::class, 'students'])->name('students');
    Route::post('courses/{course}/private', [Messaging::class, 'privateChat'])->middleware('throttle:messaging-private')->name('private');
    Route::post('courses/{course}/groups', [Messaging::class, 'group'])->middleware('throttle:messaging-group')->name('groups');
    Route::get('conversations', [Messaging::class, 'index'])->name('conversations');
    Route::get('conversations/{conversation}', [Messaging::class, 'show'])->name('show');
    Route::put('conversations/{conversation}/members', [Messaging::class, 'members'])->middleware('throttle:messaging-group')->name('members');
    Route::get('conversations/{conversation}/messages', [Messaging::class, 'messages'])->name('messages');
    Route::post('conversations/{conversation}/messages', [Messaging::class, 'send'])->middleware('throttle:messaging-send')->name('send');
    Route::post('conversations/{conversation}/read', [Messaging::class, 'read'])->name('read');
    Route::get('conversations/{conversation}/events', [Messaging::class, 'events'])->name('events');
    Route::get('notifications', [Messaging::class, 'notifications'])->name('notifications');
    Route::get('messages/{message}', [Messaging::class, 'messageDetail'])->name('message');
    Route::delete('messages/{message}', [Messaging::class, 'delete'])->middleware('throttle:messaging-send')->name('delete');
    Route::post('messages/{message}/reactions', [Messaging::class, 'react'])->middleware('throttle:messaging-reaction')->name('reactions');
    Route::get('attachments/{attachment}', [Messaging::class, 'attachment'])->name('attachments.show');
});
