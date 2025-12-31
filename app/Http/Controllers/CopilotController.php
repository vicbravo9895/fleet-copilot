<?php

namespace App\Http\Controllers;

use App\Models\ChatMessage;
use App\Models\Conversation;
use App\Neuron\FleetAgent;
use App\Neuron\Observers\TokenTrackingObserver;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Inertia\Inertia;
use NeuronAI\Chat\Messages\UserMessage;
use NeuronAI\Chat\Messages\ToolCallMessage;
use NeuronAI\Chat\Messages\ToolCallResultMessage;
use NeuronAI\Observability\LogObserver;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CopilotController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        
        $conversations = Conversation::where('user_id', $user->id)
            ->orderBy('updated_at', 'desc')
            ->get(['id', 'thread_id', 'title', 'created_at', 'updated_at']);
        
        return Inertia::render('copilot', [
            'conversations' => $conversations,
            'currentConversation' => null,
            'messages' => [],
        ]);
    }

    public function show(Request $request, string $threadId)
    {
        $user = $request->user();
        
        $conversations = Conversation::where('user_id', $user->id)
            ->orderBy('updated_at', 'desc')
            ->get(['id', 'thread_id', 'title', 'created_at', 'updated_at']);
        
        $currentConversation = Conversation::where('user_id', $user->id)
            ->where('thread_id', $threadId)
            ->first();
        
        if (!$currentConversation) {
            return redirect()->route('copilot.index');
        }
        
        $messages = $this->getFormattedMessages($threadId);
        
        return Inertia::render('copilot', [
            'conversations' => $conversations,
            'currentConversation' => [
                ...$currentConversation->toArray(),
                'total_tokens' => $currentConversation->total_tokens,
            ],
            'messages' => $messages,
        ]);
    }

    public function send(Request $request): StreamedResponse
    {
        $request->validate([
            'message' => 'required|string|max:10000',
            'thread_id' => 'nullable|string',
        ]);
        
        $user = $request->user();
        $message = $request->input('message');
        $threadId = $request->input('thread_id');
        $isNewConversation = false;
        
        // Si no hay thread_id, crear una nueva conversación
        if (!$threadId) {
            $threadId = Str::uuid()->toString();
            $isNewConversation = true;
            
            // Generar título a partir del primer mensaje (máximo 50 caracteres)
            $title = Str::limit($message, 50);
            
            Conversation::create([
                'thread_id' => $threadId,
                'user_id' => $user->id,
                'title' => $title,
            ]);
        }
        
        // Actualizar el timestamp de la conversación
        Conversation::where('thread_id', $threadId)
            ->where('user_id', $user->id)
            ->update(['updated_at' => now()]);
        
        $userId = $user->id;
        $model = config('services.openai.standard_model');

        return new StreamedResponse(function () use ($message, $threadId, $isNewConversation, $userId, $model) {
            // Enviar evento inicial con thread_id
            echo "data: " . json_encode([
                'type' => 'start',
                'thread_id' => $threadId,
                'is_new_conversation' => $isNewConversation,
            ]) . "\n\n";
            ob_flush();
            flush();
            
            // Observer para tracking de tokens
            $tokenObserver = new TokenTrackingObserver(
                userId: $userId,
                threadId: $threadId,
                model: $model,
                logger: Log::channel('neuron')
            );

            // Crear el agente con el thread correcto
            $agent = (new FleetAgent())
                ->withThread($threadId)
                ->observe(new LogObserver(Log::channel('neuron')))
                ->observe($tokenObserver);
            
            // Usar streaming para obtener la respuesta chunk por chunk
            $stream = $agent->stream(new UserMessage($message));
            
            foreach ($stream as $chunk) {
                // Enviar evento cuando se está llamando a una herramienta
                if ($chunk instanceof ToolCallMessage) {
                    foreach ($chunk->getTools() as $tool) {
                        $toolName = $tool->getName();
                        $toolDescription = $this->getToolDisplayInfo($toolName);
                        
                        echo "data: " . json_encode([
                            'type' => 'tool_start',
                            'tool' => $toolName,
                            'label' => $toolDescription['label'],
                            'icon' => $toolDescription['icon'],
                        ]) . "\n\n";
                        ob_flush();
                        flush();
                    }
                    continue;
                }
                
                // Enviar evento cuando una herramienta termina
                if ($chunk instanceof ToolCallResultMessage) {
                    echo "data: " . json_encode([
                        'type' => 'tool_end',
                    ]) . "\n\n";
                    ob_flush();
                    flush();
                    continue;
                }
                
                // Only send string chunks
                if (!is_string($chunk)) {
                    continue;
                }
                
                // Enviar cada chunk como evento SSE
                echo "data: " . json_encode([
                    'type' => 'chunk',
                    'content' => $chunk,
                ]) . "\n\n";
                ob_flush();
                flush();
            }
            
            // Obtener estadísticas de tokens
            $tokenStats = $tokenObserver->getTotalTokens();

            // Enviar evento de finalización con tokens
            echo "data: " . json_encode([
                'type' => 'done',
                'thread_id' => $threadId,
                'tokens' => $tokenStats,
            ]) . "\n\n";
            ob_flush();
            flush();
            
        }, 200, [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache',
            'Connection' => 'keep-alive',
            'X-Accel-Buffering' => 'no',
        ]);
    }

    public function destroy(Request $request, string $threadId)
    {
        $user = $request->user();
        
        $conversation = Conversation::where('user_id', $user->id)
            ->where('thread_id', $threadId)
            ->first();
        
        if ($conversation) {
            // Eliminar mensajes
            ChatMessage::where('thread_id', $threadId)->delete();
            
            // Eliminar conversación
            $conversation->delete();
        }
        
        return redirect()->route('copilot.index');
    }

    private function getFormattedMessages(string $threadId): array
    {
        return ChatMessage::where('thread_id', $threadId)
            ->orderBy('created_at', 'asc')
            ->get()
            ->map(function ($message) {
                $content = $message->content;
                
                // Manejar diferentes formatos de contenido
                if (is_array($content)) {
                    // Skip tool_call and tool_call_result messages (they have no visible content)
                    $type = $content['type'] ?? null;
                    if (in_array($type, ['tool_call', 'tool_call_result'])) {
                        return null;
                    }
                    
                    $content = $content['text'] ?? (is_string($content) ? $content : null);
                }
                
                // Skip empty messages
                if (empty($content) || (is_string($content) && trim($content) === '')) {
                    return null;
                }
                
                return [
                    'id' => $message->id,
                    'role' => $message->role,
                    'content' => $content,
                    'created_at' => $message->created_at->toISOString(),
                ];
            })
            ->filter() // Remove null entries
            ->values() // Re-index array
            ->toArray();
    }

    /**
     * Obtener información de display para cada herramienta
     */
    private function getToolDisplayInfo(string $toolName): array
    {
        $tools = [
            'GetVehicles' => [
                'label' => 'Consultando vehículos de la flota...',
                'icon' => 'truck',
            ],
            'GetVehicleStats' => [
                'label' => 'Obteniendo estadísticas en tiempo real...',
                'icon' => 'activity',
            ],
            'PGSQLSchemaTool' => [
                'label' => 'Explorando estructura de datos...',
                'icon' => 'database',
            ],
            'PGSQLSelectTool' => [
                'label' => 'Buscando información...',
                'icon' => 'search',
            ],
        ];

        return $tools[$toolName] ?? [
            'label' => 'Procesando...',
            'icon' => 'loader',
        ];
    }
}
