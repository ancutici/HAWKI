<?php

namespace App\Services\AI;

use App\Models\Records\UsageRecord;
use App\Services\AI\Value\TokenUsage;
use App\Services\Logging\GraylogService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;

class UsageAnalyzerService
{

    public function __construct(
        private readonly GraylogService $graylog,
    ) {}

    public function submitUsageRecord(?TokenUsage $usage, $type, $roomId = null)
    {
        if ($usage === null) {
            return;
        }

        $user = Auth::user();
        $userId = $user->id;

        // Create a new record if none exists for today
        UsageRecord::create([
            'user_id' => $userId,
            'room_id' => $roomId,

            'prompt_tokens' => $usage->promptTokens,
            'completion_tokens' => $usage->completionTokens,
            'model' => $usage->model->getId(),
            'type' => $type,
        ]);

        $this->graylog->sendChatUsage(
            $usage,
            $type,
            $this->normalizeEmployeeType($user->employeetype ?? ''),
        );
    }

    public function summarizeAndCleanup()
    {
        $lastMonth = Carbon::now()->subMonth()->format('Y-m');

        // Updated summary logic to include the 'model' column
        $summaries = UsageRecord::selectRaw('user_id, room_id, type, model, SUM(prompt_tokens) as total_prompt_tokens, SUM(completion_tokens) as total_completion_tokens')
            ->whereMonth('created_at', Carbon::now()->subMonth()->month)
            ->whereYear('created_at', Carbon::now()->subMonth()->year)
            ->groupBy('user_id', 'room_id', 'type', 'model')
            ->get();

        foreach ($summaries as $summary) {
            // Store summaries in another table, save to a file, or perform another action
        }

        // Clean up old records
        UsageRecord::whereMonth('created_at', Carbon::now()->subMonth()->month)
            ->whereYear('created_at', Carbon::now()->subMonth()->year)
            ->delete();
    }

    private function normalizeEmployeeType(string $raw): string
    {
        if ($raw === '') {
            return 'unknown';
        }
        $parts = array_unique(array_filter(array_map('trim', explode(',', $raw))));
        if (count($parts) > 1) {
            return 'student-staff';
        }
        return reset($parts) ?: 'unknown';
    }

}
