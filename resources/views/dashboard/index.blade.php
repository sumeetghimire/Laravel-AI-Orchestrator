<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AI Orchestrator Dashboard</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, sans-serif;
            background: #f5f7fa;
            padding: 20px;
        }
        .container { max-width: 1400px; margin: 0 auto; }
        .header {
            background: white;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .header h1 { color: #1a202c; margin-bottom: 10px; display: flex; align-items: center; gap: 12px; }
        .header .logo { height: 60px; width: auto; }
        .filters {
            display: flex;
            gap: 10px;
            margin-top: 15px;
        }
        .filters select, .filters input {
            padding: 8px 12px;
            border: 1px solid #e2e8f0;
            border-radius: 4px;
            font-size: 14px;
        }
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 20px;
        }
        .stat-card {
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .stat-card h3 {
            color: #718096;
            font-size: 12px;
            text-transform: uppercase;
            margin-bottom: 8px;
        }
        .stat-card .value {
            font-size: 28px;
            font-weight: bold;
            color: #1a202c;
        }
        .table-container {
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
        }
        th, td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #e2e8f0;
        }
        th {
            background: #f7fafc;
            font-weight: 600;
            color: #4a5568;
            font-size: 12px;
            text-transform: uppercase;
        }
        .badge {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 11px;
            font-weight: 600;
        }
        .badge-success { background: #c6f6d5; color: #22543d; }
        .badge-info { background: #bee3f8; color: #2c5282; }
        .btn {
            display: inline-block;
            padding: 8px 16px;
            background: #4299e1;
            color: white;
            text-decoration: none;
            border-radius: 4px;
            font-size: 14px;
        }
        .btn:hover { background: #3182ce; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>
                <img src="{{ asset('vendor/ai-orchestrator/images/logo.png') }}" alt="AI Orchestrator" class="logo" onerror="this.style.display='none'">
                AI Orchestrator Dashboard
            </h1>
            <p>Monitor your AI usage, costs, and performance</p>
            <div class="filters">
                <select onchange="window.location.href='?period='+this.value+'&user_id={{ $userId ?? '' }}'">
                    <option value="today" {{ $period === 'today' ? 'selected' : '' }}>Today</option>
                    <option value="week" {{ $period === 'week' ? 'selected' : '' }}>This Week</option>
                    <option value="month" {{ $period === 'month' ? 'selected' : '' }}>This Month</option>
                    <option value="all" {{ $period === 'all' ? 'selected' : '' }}>All Time</option>
                </select>
                <a href="{{ route('ai-orchestrator.logs') }}" class="btn">View All Logs</a>
            </div>
        </div>

        <div class="stats-grid">
            <div class="stat-card">
                <h3>Total Cost</h3>
                <div class="value">${{ number_format($totalCost, 4) }}</div>
            </div>
            <div class="stat-card">
                <h3>Total Tokens</h3>
                <div class="value">{{ number_format($totalTokens) }}</div>
            </div>
            <div class="stat-card">
                <h3>Total Requests</h3>
                <div class="value">{{ number_format($totalRequests) }}</div>
            </div>
            <div class="stat-card">
                <h3>Cached Requests</h3>
                <div class="value">{{ number_format($cachedRequests) }}</div>
            </div>
            <div class="stat-card">
                <h3>Avg Duration</h3>
                <div class="value">{{ number_format($avgDuration, 2) }}s</div>
            </div>
        </div>

        <div class="table-container">
            <h2 style="margin-bottom: 15px;">Provider Breakdown</h2>
            <table>
                <thead>
                    <tr>
                        <th>Provider</th>
                        <th>Requests</th>
                        <th>Tokens</th>
                        <th>Cost</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($providerStats as $stat)
                    <tr>
                        <td><strong>{{ $stat->provider }}</strong></td>
                        <td>{{ $stat->count }}</td>
                        <td>{{ number_format($stat->total_tokens) }}</td>
                        <td>${{ number_format($stat->total_cost, 4) }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="table-container">
            <h2 style="margin-bottom: 15px;">Recent Requests</h2>
            <table>
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Provider</th>
                        <th>Model</th>
                        <th>Tokens</th>
                        <th>Cost</th>
                        <th>Cached</th>
                        <th>Duration</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($recentLogs as $log)
                    <tr>
                        <td>{{ $log->created_at->format('Y-m-d H:i:s') }}</td>
                        <td>{{ $log->provider }}</td>
                        <td>{{ $log->model }}</td>
                        <td>{{ number_format($log->tokens) }}</td>
                        <td>${{ number_format($log->cost, 4) }}</td>
                        <td>
                            @if($log->cached)
                                <span class="badge badge-success">Yes</span>
                            @else
                                <span class="badge badge-info">No</span>
                            @endif
                        </td>
                        <td>{{ number_format($log->duration ?? 0, 2) }}s</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</body>
</html>

