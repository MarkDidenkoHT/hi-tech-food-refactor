<?php

namespace App\Http\Controllers\Api\Admin;

use App\Console\Commands\SendPhotoQuestionReminders;
use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CronSettingController extends Controller
{
    /**
     * Show the current state of the photo question reminder cron.
     */
    public function show(): JsonResponse
    {
        return response()->json([
            'photo_question_reminders_enabled' => Setting::getBool(SendPhotoQuestionReminders::SETTING_KEY),
        ]);
    }

    /**
     * Enable or disable the photo question reminder cron.
     */
    public function update(Request $request): JsonResponse
    {
        $data = $request->validate([
            'photo_question_reminders_enabled' => ['required', 'boolean'],
        ]);

        Setting::setBool(SendPhotoQuestionReminders::SETTING_KEY, $data['photo_question_reminders_enabled']);

        return response()->json([
            'photo_question_reminders_enabled' => $data['photo_question_reminders_enabled'],
        ]);
    }
}
