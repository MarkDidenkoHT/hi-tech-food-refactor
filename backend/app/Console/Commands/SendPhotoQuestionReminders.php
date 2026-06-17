<?php

namespace App\Console\Commands;

use App\Models\Restaurant;
use App\Models\Setting;
use App\Services\Telegram\TelegramBotService;
use Illuminate\Console\Command;

class SendPhotoQuestionReminders extends Command
{
    /**
     * The setting key controlling whether this reminder is sent.
     */
    public const SETTING_KEY = 'photo_question_reminders_enabled';

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:send-photo-question-reminders';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Send each restaurant its next photo question via Telegram, one at a time';

    public function handle(TelegramBotService $telegram): int
    {
        if (! Setting::getBool(self::SETTING_KEY)) {
            $this->comment('Photo question reminders are disabled, skipping.');

            return self::SUCCESS;
        }

        $restaurants = Restaurant::where('is_active', true)
            ->whereNotNull('telegram_group_chat_id')
            ->with(['photoQuestions' => function ($query) {
                $query->where('is_active', true)->orderBy('sort_order')->orderBy('id');
            }])
            ->get();

        foreach ($restaurants as $restaurant) {
            $questions = $restaurant->photoQuestions;

            if ($questions->isEmpty()) {
                continue;
            }

            $currentIndex = $questions->search(fn ($question) => $question->id === $restaurant->last_photo_question_id);
            $nextIndex = $currentIndex === false ? 0 : ($currentIndex + 1) % $questions->count();
            $next = $questions->get($nextIndex);

            $telegram->sendMessage($restaurant->telegram_group_chat_id, "📸 <b>Фото-вопрос</b>\n".e($next->question));

            $restaurant->update(['last_photo_question_id' => $next->id]);
        }

        return self::SUCCESS;
    }
}
