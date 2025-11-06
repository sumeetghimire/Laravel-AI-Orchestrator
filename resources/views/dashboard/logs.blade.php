<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AI Orchestrator - Logs</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
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
        .table-container {
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        table {
            width: 100%;
            border-collapse: collapse;
        }
        th, td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #e2e8f0;
            font-size: 14px;
        }
        th {
            background: #f7fafc;
            font-weight: 600;
            color: #4a5568;
            text-transform: uppercase;
            font-size: 12px;
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
            margin-top: 10px;
        }
        .btn:hover { background: #3182ce; }
        .pagination {
            margin-top: 20px;
            display: flex;
            gap: 10px;
        }
        .pagination a {
            padding: 8px 12px;
            background: white;
            border: 1px solid #e2e8f0;
            border-radius: 4px;
            text-decoration: none;
            color: #4a5568;
        }
        .pagination a:hover {
            background: #f7fafc;
        }
        .prompt-preview {
            max-width: 300px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>
                <img src="{{ asset('vendor/ai-orchestrator/images/logo.png') }}" alt="AI Orchestrator" class="logo" onerror="this.style.display='none'">
                AI Orchestrator - Request Logs
            </h1>
            <a href="{{ route('ai-orchestrator.dashboard') }}" class="btn">← Back to Dashboard</a>
        </div>

        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Date</th>
                        <th>Provider</th>
                        <th>Model</th>
                        <th>Prompt</th>
                        <th>Tokens</th>
                        <th>Cost</th>
                        <th>Cached</th>
                        <th>Duration</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($logs as $log)
                    <tr>
                        <td>{{ $log->id }}</td>
                        <td>{{ $log->created_at->format('Y-m-d H:i:s') }}</td>
                        <td><strong>{{ $log->provider }}</strong></td>
                        <td>{{ $log->model }}</td>
                        <td class="prompt-preview" title="{{ $log->prompt }}">
                            {{ \Illuminate\Support\Str::limit($log->prompt, 50) }}
                        </td>
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

            <div class="pagination">
                {{ $logs->links() }}
            </div>
        </div>
    </div>
</body>
</html>

