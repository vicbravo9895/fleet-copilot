import { index as copilotIndex, show } from '@/actions/App/Http/Controllers/CopilotController';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import AppLayout from '@/layouts/app-layout';
import { cn } from '@/lib/utils';
import { dashboard } from '@/routes';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, usePage } from '@inertiajs/react';
import {
    ArrowRight,
    Bot,
    Calendar,
    Coins,
    MessageCircle,
    MessagesSquare,
    Sparkles,
    TrendingUp,
    User,
    Zap,
} from 'lucide-react';

interface Stats {
    totalConversations: number;
    totalMessages: number;
    conversationsToday: number;
    messagesToday: number;
    userMessages: number;
    assistantMessages: number;
}

interface Activity {
    day: string;
    messages: number;
}

interface TokenStats {
    total: number;
    today: number;
    thisWeek: number;
    thisMonth: number;
    inputTokens: number;
    outputTokens: number;
}

interface TokenByDay {
    day: string;
    tokens: number;
}

interface RecentConversation {
    id: number;
    thread_id: string;
    title: string;
    message_count: number;
    last_message: {
        role: string;
        preview: string;
    } | null;
    updated_at: string;
}

interface DashboardProps {
    stats: Stats;
    tokenStats: TokenStats;
    tokensByDay: TokenByDay[];
    activity: Activity[];
    recentConversations: RecentConversation[];
}

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Dashboard',
        href: dashboard().url,
    },
];

export default function Dashboard() {
    const { stats, tokenStats, tokensByDay, activity, recentConversations } = usePage<{
        props: DashboardProps;
    }>().props as unknown as DashboardProps;

    const maxMessages = Math.max(...activity.map((a) => a.messages), 1);
    const maxTokens = Math.max(...tokensByDay.map((t) => t.tokens), 1);

    // Formatear números grandes
    const formatNumber = (num: number) => {
        if (num >= 1000000) return `${(num / 1000000).toFixed(1)}M`;
        if (num >= 1000) return `${(num / 1000).toFixed(1)}K`;
        return num.toLocaleString();
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Dashboard" />

            <div className="flex h-full flex-1 flex-col gap-4 sm:gap-6 p-4 sm:p-6">
                {/* Header */}
                <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <h1 className="text-xl sm:text-2xl font-bold tracking-tight">Dashboard</h1>
                        <p className="text-muted-foreground text-sm sm:text-base">
                            Resumen de tu actividad con el Copilot
                        </p>
                    </div>
                    <Link
                        href={copilotIndex.url()}
                        className="bg-primary text-primary-foreground hover:bg-primary/90 inline-flex items-center justify-center gap-2 rounded-lg px-4 py-2.5 text-sm font-medium transition-colors w-full sm:w-auto"
                    >
                        <Sparkles className="size-4" />
                        Nuevo chat
                    </Link>
                </div>

                {/* Token Stats */}
                {tokenStats.total > 0 && (
                    <Card className="border-none bg-gradient-to-r from-amber-500/10 via-orange-500/10 to-rose-500/10">
                        <CardContent className="p-4 sm:p-6">
                            <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                                <div className="flex items-center gap-3 sm:gap-4">
                                    <div className="flex size-10 sm:size-12 items-center justify-center rounded-full bg-gradient-to-br from-amber-500/20 to-orange-500/20">
                                        <Coins className="size-5 sm:size-6 text-amber-600" />
                                    </div>
                                    <div>
                                        <p className="text-muted-foreground text-xs sm:text-sm font-medium">Uso de Tokens</p>
                                        <p className="text-2xl sm:text-3xl font-bold">{formatNumber(tokenStats.total)}</p>
                                    </div>
                                </div>
                                <div className="grid grid-cols-3 gap-3 sm:gap-6 text-center">
                                    <div>
                                        <p className="text-muted-foreground text-xs">Hoy</p>
                                        <p className="text-base sm:text-lg font-semibold">{formatNumber(tokenStats.today)}</p>
                                    </div>
                                    <div>
                                        <p className="text-muted-foreground text-xs">Semana</p>
                                        <p className="text-base sm:text-lg font-semibold">{formatNumber(tokenStats.thisWeek)}</p>
                                    </div>
                                    <div>
                                        <p className="text-muted-foreground text-xs">Mes</p>
                                        <p className="text-base sm:text-lg font-semibold">{formatNumber(tokenStats.thisMonth)}</p>
                                    </div>
                                </div>
                            </div>
                            <div className="mt-3 sm:mt-4 flex items-center gap-3 sm:gap-4">
                                <div className="flex items-center gap-2 text-xs sm:text-sm">
                                    <div className="size-2 rounded-full bg-blue-500"></div>
                                    <span className="text-muted-foreground">Input: {formatNumber(tokenStats.inputTokens)}</span>
                                </div>
                                <div className="flex items-center gap-2 text-xs sm:text-sm">
                                    <div className="size-2 rounded-full bg-green-500"></div>
                                    <span className="text-muted-foreground">Output: {formatNumber(tokenStats.outputTokens)}</span>
                                </div>
                            </div>
                        </CardContent>
                    </Card>
                )}

                {/* Stats Grid */}
                <div className="grid grid-cols-2 gap-3 sm:gap-4 lg:grid-cols-4">
                    <Card className="border-none bg-gradient-to-br from-violet-500/10 to-purple-500/10">
                        <CardHeader className="flex flex-row items-center justify-between p-3 sm:p-6 pb-1 sm:pb-2">
                            <CardTitle className="text-xs sm:text-sm font-medium">
                                Conversaciones
                            </CardTitle>
                            <MessagesSquare className="text-muted-foreground size-3.5 sm:size-4" />
                        </CardHeader>
                        <CardContent className="p-3 sm:p-6 pt-0">
                            <div className="text-2xl sm:text-3xl font-bold">
                                {stats.totalConversations}
                            </div>
                            <p className="text-muted-foreground text-[10px] sm:text-xs">
                                +{stats.conversationsToday} hoy
                            </p>
                        </CardContent>
                    </Card>

                    <Card className="border-none bg-gradient-to-br from-blue-500/10 to-cyan-500/10">
                        <CardHeader className="flex flex-row items-center justify-between p-3 sm:p-6 pb-1 sm:pb-2">
                            <CardTitle className="text-xs sm:text-sm font-medium">
                                Mensajes
                            </CardTitle>
                            <MessageCircle className="text-muted-foreground size-3.5 sm:size-4" />
                        </CardHeader>
                        <CardContent className="p-3 sm:p-6 pt-0">
                            <div className="text-2xl sm:text-3xl font-bold">{stats.totalMessages}</div>
                            <p className="text-muted-foreground text-[10px] sm:text-xs">
                                +{stats.messagesToday} hoy
                            </p>
                        </CardContent>
                    </Card>

                    <Card className="border-none bg-gradient-to-br from-emerald-500/10 to-green-500/10">
                        <CardHeader className="flex flex-row items-center justify-between p-3 sm:p-6 pb-1 sm:pb-2">
                            <CardTitle className="text-xs sm:text-sm font-medium">
                                Tus Mensajes
                            </CardTitle>
                            <User className="text-muted-foreground size-3.5 sm:size-4" />
                        </CardHeader>
                        <CardContent className="p-3 sm:p-6 pt-0">
                            <div className="text-2xl sm:text-3xl font-bold">{stats.userMessages}</div>
                            <p className="text-muted-foreground text-[10px] sm:text-xs">
                                Preguntas realizadas
                            </p>
                        </CardContent>
                    </Card>

                    <Card className="border-none bg-gradient-to-br from-amber-500/10 to-orange-500/10">
                        <CardHeader className="flex flex-row items-center justify-between p-3 sm:p-6 pb-1 sm:pb-2">
                            <CardTitle className="text-xs sm:text-sm font-medium">
                                Respuestas
                            </CardTitle>
                            <Bot className="text-muted-foreground size-3.5 sm:size-4" />
                        </CardHeader>
                        <CardContent className="p-3 sm:p-6 pt-0">
                            <div className="text-2xl sm:text-3xl font-bold">
                                {stats.assistantMessages}
                            </div>
                            <p className="text-muted-foreground text-[10px] sm:text-xs">
                                Respuestas generadas
                            </p>
                        </CardContent>
                    </Card>
                </div>

                {/* Charts Grid */}
                <div className="grid gap-4 sm:gap-6 md:grid-cols-2 lg:grid-cols-3">
                    {/* Activity Chart */}
                    <Card>
                        <CardHeader>
                            <div className="flex items-center gap-2">
                                <TrendingUp className="text-primary size-5" />
                                <CardTitle>Mensajes</CardTitle>
                            </div>
                            <CardDescription>
                                Últimos 7 días
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <div className="flex h-[160px] items-end justify-between gap-1">
                                {activity.map((day, index) => (
                                    <div
                                        key={index}
                                        className="flex flex-1 flex-col items-center gap-1"
                                    >
                                        <div className="relative flex h-[120px] w-full items-end justify-center">
                                            <div
                                                className={cn(
                                                    'w-full max-w-[30px] rounded-t-md transition-all duration-500',
                                                    day.messages > 0
                                                        ? 'bg-gradient-to-t from-primary/60 to-primary'
                                                        : 'bg-muted',
                                                )}
                                                style={{
                                                    height: `${Math.max((day.messages / maxMessages) * 100, 8)}%`,
                                                }}
                                            />
                                            {day.messages > 0 && (
                                                <span className="text-muted-foreground absolute -top-5 text-[10px] font-medium">
                                                    {day.messages}
                                                </span>
                                            )}
                                        </div>
                                        <span className="text-muted-foreground text-[10px]">
                                            {day.day}
                                        </span>
                                    </div>
                                ))}
                            </div>
                        </CardContent>
                    </Card>

                    {/* Token Usage Chart */}
                    <Card>
                        <CardHeader>
                            <div className="flex items-center gap-2">
                                <Zap className="size-5 text-amber-500" />
                                <CardTitle>Tokens</CardTitle>
                            </div>
                            <CardDescription>
                                Uso últimos 7 días
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <div className="flex h-[160px] items-end justify-between gap-1">
                                {tokensByDay.map((day, index) => (
                                    <div
                                        key={index}
                                        className="flex flex-1 flex-col items-center gap-1"
                                    >
                                        <div className="relative flex h-[120px] w-full items-end justify-center">
                                            <div
                                                className={cn(
                                                    'w-full max-w-[30px] rounded-t-md transition-all duration-500',
                                                    day.tokens > 0
                                                        ? 'bg-gradient-to-t from-amber-500/60 to-amber-500'
                                                        : 'bg-muted',
                                                )}
                                                style={{
                                                    height: `${Math.max((day.tokens / maxTokens) * 100, 8)}%`,
                                                }}
                                            />
                                            {day.tokens > 0 && (
                                                <span className="text-muted-foreground absolute -top-5 text-[10px] font-medium">
                                                    {formatNumber(day.tokens)}
                                                </span>
                                            )}
                                        </div>
                                        <span className="text-muted-foreground text-[10px]">
                                            {day.day}
                                        </span>
                                    </div>
                                ))}
                            </div>
                        </CardContent>
                    </Card>

                    {/* Recent Conversations */}
                    <Card>
                        <CardHeader>
                            <div className="flex items-center justify-between">
                                <div className="flex items-center gap-2">
                                    <Calendar className="text-primary size-5" />
                                    <CardTitle>Recientes</CardTitle>
                                </div>
                                <Link
                                    href={copilotIndex.url()}
                                    className="text-primary hover:text-primary/80 flex items-center gap-1 text-xs font-medium"
                                >
                                    Ver todas
                                    <ArrowRight className="size-3" />
                                </Link>
                            </div>
                        </CardHeader>
                        <CardContent>
                            {recentConversations.length === 0 ? (
                                <div className="flex flex-col items-center justify-center py-4 text-center">
                                    <MessagesSquare className="text-muted-foreground mb-2 size-6" />
                                    <p className="text-muted-foreground text-xs">
                                        No tienes conversaciones
                                    </p>
                                </div>
                            ) : (
                                <div className="space-y-2">
                                    {recentConversations.slice(0, 4).map((conv) => (
                                        <Link
                                            key={conv.thread_id}
                                            href={show.url(conv.thread_id)}
                                            className="hover:bg-muted/50 group flex items-center gap-2 rounded-lg p-2 transition-colors"
                                        >
                                            <div className="bg-primary/10 flex size-8 flex-shrink-0 items-center justify-center rounded-full">
                                                <MessageCircle className="text-primary size-4" />
                                            </div>
                                            <div className="min-w-0 flex-1">
                                                <p className="truncate text-sm font-medium">
                                                    {conv.title}
                                                </p>
                                                <p className="text-muted-foreground text-xs">
                                                    {conv.message_count} mensajes · {conv.updated_at}
                                                </p>
                                            </div>
                                        </Link>
                                    ))}
                                </div>
                            )}
                        </CardContent>
                    </Card>
                </div>

                {/* Quick Tips */}
                <Card className="bg-gradient-to-r from-primary/5 via-primary/10 to-primary/5">
                    <CardContent className="flex flex-col sm:flex-row items-start sm:items-center gap-4 p-4 sm:p-6">
                        <div className="bg-primary/10 flex size-10 sm:size-12 flex-shrink-0 items-center justify-center rounded-full">
                            <Sparkles className="text-primary size-5 sm:size-6" />
                        </div>
                        <div className="flex-1">
                            <h3 className="font-semibold text-sm sm:text-base">Consejo del día</h3>
                            <p className="text-muted-foreground text-xs sm:text-sm">
                                Puedes hacer preguntas específicas sobre tu flota para obtener
                                respuestas más precisas. Por ejemplo: "¿Cuáles vehículos tienen
                                más de 50,000 km recorridos?"
                            </p>
                        </div>
                        <Link
                            href={copilotIndex.url()}
                            className="bg-primary text-primary-foreground hover:bg-primary/90 flex-shrink-0 rounded-lg px-4 py-2.5 text-sm font-medium transition-colors w-full sm:w-auto text-center"
                        >
                            Probar ahora
                        </Link>
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}
