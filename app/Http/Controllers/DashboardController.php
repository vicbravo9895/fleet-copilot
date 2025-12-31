<?php

namespace App\Http\Controllers;

use App\Models\ChatMessage;
use App\Models\Conversation;
use App\Models\TokenUsage;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $today = Carbon::today();
        $lastWeek = Carbon::now()->subDays(7);

        // Estadísticas generales
        $totalConversations = Conversation::where('user_id', $user->id)->count();
        
        $totalMessages = ChatMessage::whereIn('thread_id', function ($query) use ($user) {
            $query->select('thread_id')
                ->from('conversations')
                ->where('user_id', $user->id);
        })->count();

        $conversationsToday = Conversation::where('user_id', $user->id)
            ->whereDate('created_at', $today)
            ->count();

        $messagesToday = ChatMessage::whereIn('thread_id', function ($query) use ($user) {
            $query->select('thread_id')
                ->from('conversations')
                ->where('user_id', $user->id);
        })->whereDate('created_at', $today)->count();

        // Actividad de los últimos 7 días
        $activityData = ChatMessage::whereIn('thread_id', function ($query) use ($user) {
            $query->select('thread_id')
                ->from('conversations')
                ->where('user_id', $user->id);
        })
            ->where('created_at', '>=', $lastWeek)
            ->select(DB::raw('DATE(created_at) as date'), DB::raw('COUNT(*) as count'))
            ->groupBy('date')
            ->orderBy('date')
            ->get()
            ->keyBy('date');

        // Llenar los días sin actividad con 0
        $activity = [];
        for ($i = 6; $i >= 0; $i--) {
            $date = Carbon::now()->subDays($i)->format('Y-m-d');
            $dayName = Carbon::now()->subDays($i)->locale('es')->isoFormat('ddd');
            $activity[] = [
                'day' => ucfirst($dayName),
                'messages' => $activityData->get($date)?->count ?? 0,
            ];
        }

        // Últimas conversaciones
        $recentConversations = Conversation::where('user_id', $user->id)
            ->orderBy('updated_at', 'desc')
            ->limit(5)
            ->get()
            ->map(function ($conv) {
                $lastMessage = ChatMessage::where('thread_id', $conv->thread_id)
                    ->orderBy('created_at', 'desc')
                    ->first();
                
                $messageCount = ChatMessage::where('thread_id', $conv->thread_id)->count();
                
                return [
                    'id' => $conv->id,
                    'thread_id' => $conv->thread_id,
                    'title' => $conv->title,
                    'message_count' => $messageCount,
                    'last_message' => $lastMessage ? [
                        'role' => $lastMessage->role,
                        'preview' => is_array($lastMessage->content) 
                            ? \Illuminate\Support\Str::limit($lastMessage->content['text'] ?? '', 100)
                            : \Illuminate\Support\Str::limit($lastMessage->content ?? '', 100),
                    ] : null,
                    'updated_at' => $conv->updated_at->diffForHumans(),
                ];
            });

        // Distribución de mensajes por rol
        $messagesByRole = ChatMessage::whereIn('thread_id', function ($query) use ($user) {
            $query->select('thread_id')
                ->from('conversations')
                ->where('user_id', $user->id);
        })
            ->select('role', DB::raw('COUNT(*) as count'))
            ->groupBy('role')
            ->get()
            ->pluck('count', 'role')
            ->toArray();

        // Estadísticas de tokens
        $tokenStats = [
            'total' => $user->total_tokens_used ?? 0,
            'today' => TokenUsage::where('user_id', $user->id)
                ->whereDate('created_at', $today)
                ->sum('total_tokens'),
            'thisWeek' => TokenUsage::where('user_id', $user->id)
                ->where('created_at', '>=', $lastWeek)
                ->sum('total_tokens'),
            'thisMonth' => TokenUsage::where('user_id', $user->id)
                ->where('created_at', '>=', Carbon::now()->startOfMonth())
                ->sum('total_tokens'),
            'inputTokens' => TokenUsage::where('user_id', $user->id)->sum('input_tokens'),
            'outputTokens' => TokenUsage::where('user_id', $user->id)->sum('output_tokens'),
        ];

        // Uso de tokens por día (últimos 7 días)
        $tokenActivity = TokenUsage::where('user_id', $user->id)
            ->where('created_at', '>=', $lastWeek)
            ->select(DB::raw('DATE(created_at) as date'), DB::raw('SUM(total_tokens) as tokens'))
            ->groupBy('date')
            ->orderBy('date')
            ->get()
            ->keyBy('date');

        // Llenar los días sin actividad con 0
        $tokensByDay = [];
        for ($i = 6; $i >= 0; $i--) {
            $date = Carbon::now()->subDays($i)->format('Y-m-d');
            $dayName = Carbon::now()->subDays($i)->locale('es')->isoFormat('ddd');
            $tokensByDay[] = [
                'day' => ucfirst($dayName),
                'tokens' => (int) ($tokenActivity->get($date)?->tokens ?? 0),
            ];
        }

        return Inertia::render('dashboard', [
            'stats' => [
                'totalConversations' => $totalConversations,
                'totalMessages' => $totalMessages,
                'conversationsToday' => $conversationsToday,
                'messagesToday' => $messagesToday,
                'userMessages' => $messagesByRole['user'] ?? 0,
                'assistantMessages' => $messagesByRole['assistant'] ?? 0,
            ],
            'tokenStats' => $tokenStats,
            'tokensByDay' => $tokensByDay,
            'activity' => $activity,
            'recentConversations' => $recentConversations,
        ]);
    }
}

