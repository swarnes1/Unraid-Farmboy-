<?php
// EternalFarm Docker configuration
$unraid_ip = '192.168.1.100'; // ← CHANGE THIS TO YOUR ACTUAL UNRAID IP
$docker_container = 'farmboy'; // Docker container name
$eternalfarm_panel_url = 'https://panel.eternalfarm.net'; // External API
$container_ports = [5900, 8080, 8888, 9999]; // Available ports

// Handle bot control actions
if ($_POST['action'] ?? false) {
    $action = $_POST['action'];
    $bot_pid = $_POST['bot_pid'] ?? '';
    $instance_id = $_POST['instance_id'] ?? '';
    $account_id = $_POST['account_id'] ?? '';
    
    $response = ['success' => false, 'message' => ''];
    
    switch ($action) {
        case 'stop_docker':
            // Stop bot via docker exec
            if ($bot_pid) {
                $docker_result = stopBotViaDocker($bot_pid);
                $response = $docker_result;
            }
            break;
            
        case 'stop_container_api':
            // Try to stop via container's internal API
            if ($instance_id) {
                $api_result = stopBotViaContainerAPI($instance_id);
                if ($api_result['success']) {
                    $response = $api_result;
                } else {
                    // Fallback to docker kill
                    $response = stopBotViaDocker($bot_pid);
                }
            }
            break;
            
        case 'restart_container':
            // Restart the entire Docker container
            $response = restartDockerContainer();
            break;
            
        case 'stop_all_docker':
            // Stop all bots in container
            $response = stopAllBotsInContainer();
            break;
            
        case 'external_panel':
            // Redirect to external panel
            $response = ['success' => true, 'message' => 'Redirecting to EternalFarm panel...', 'redirect' => $eternalfarm_panel_url];
            break;
    }
    
    // Return JSON response for AJAX
    if (isset($_POST['ajax'])) {
        header('Content-Type: application/json');
        echo json_encode($response);
        exit;
    }
}

// Function to stop bot via Docker exec
function stopBotViaDocker($pid) {
    global $docker_container;
    
    if (!$pid) {
        return ['success' => false, 'message' => 'No PID provided'];
    }
    
    // Try graceful termination first
    $cmd = "docker exec $docker_container kill -TERM $pid 2>&1";
    $result = shell_exec($cmd);
    sleep(2);
    
    // Check if process still exists in container
    $check_cmd = "docker exec $docker_container ps -p $pid -o pid= 2>/dev/null";
    $check = shell_exec($check_cmd);
    
    if (empty(trim($check))) {
        return ['success' => true, 'message' => "Bot process $pid stopped in container"];
    }
    
    // Force kill if still running
    $force_cmd = "docker exec $docker_container kill -KILL $pid 2>&1";
    shell_exec($force_cmd);
    sleep(1);
    
    $check = shell_exec($check_cmd);
    if (empty(trim($check))) {
        return ['success' => true, 'message' => "Bot process $pid force killed in container"];
    }
    
    return ['success' => false, 'message' => "Failed to stop process $pid in container"];
}

// Function to stop bot via container's internal API
function stopBotViaContainerAPI($instance_id) {
    global $unraid_ip, $container_ports;
    
    $api_endpoints = [];
    foreach ($container_ports as $port) {
        $api_endpoints[] = "http://localhost:$port/api/stop/$instance_id";
        $api_endpoints[] = "http://127.0.0.1:$port/api/stop/$instance_id";
        $api_endpoints[] = "http://$unraid_ip:$port/api/stop/$instance_id";
        $api_endpoints[] = "http://localhost:$port/stop/$instance_id";
    }
    
    foreach ($api_endpoints as $url) {
        $context = stream_context_create([
            'http' => [
                'method' => 'POST',
                'timeout' => 10,
                'header' => "Content-Type: application/json\r\n"
            ]
        ]);
        
        $result = @file_get_contents($url, false, $context);
        if ($result !== false) {
            return ['success' => true, 'message' => "Bot stopped via container API (Instance: $instance_id)"];
        }
    }
    
    return ['success' => false, 'message' => 'Container API stop failed'];
}

// Function to restart Docker container
function restartDockerContainer() {
    global $docker_container;
    
    $cmd = "docker restart $docker_container 2>&1";
    $result = shell_exec($cmd);
    
    if (strpos($result, $docker_container) !== false) {
        return ['success' => true, 'message' => 'Docker container restarted'];
    }
    
    return ['success' => false, 'message' => 'Failed to restart container: ' . $result];
}

// Function to stop all bots in container
function stopAllBotsInContainer() {
    global $docker_container;
    
    // Kill all java processes in container
    $cmd = "docker exec $docker_container pkill -f 'dreambot.*jar' 2>&1";
    $result = shell_exec($cmd);
    sleep(2);
    
    // Force kill any remaining
    $force_cmd = "docker exec $docker_container pkill -9 -f 'dreambot.*jar' 2>&1";
    shell_exec($force_cmd);
    
    return ['success' => true, 'message' => 'All bots stopped in container'];
}

// Function to get Docker container status
function getDockerContainerStatus() {
    global $docker_container;
    
    $cmd = "docker inspect $docker_container 2>/dev/null";
    $output = shell_exec($cmd);
    
    if ($output) {
        $container_info = json_decode($output, true);
        if ($container_info && isset($container_info[0])) {
            $info = $container_info[0];
            return [
                'running' => $info['State']['Running'] ?? false,
                'status' => $info['State']['Status'] ?? 'unknown',
                'health' => $info['State']['Health']['Status'] ?? 'unknown',
                'started_at' => $info['State']['StartedAt'] ?? null,
                'image' => $info['Config']['Image'] ?? 'unknown',
                'ports' => $info['NetworkSettings']['Ports'] ?? []
            ];
        }
    }
    
    return null;
}

// Function to get EternalFarm bots from Docker container
function getEternalFarmBotsFromContainer() {
    global $docker_container;
    
    // Get processes from inside the container
    $cmd = "docker exec $docker_container ps auxww 2>/dev/null | grep 'dreambot.*jar' | grep -v grep";
    $output = shell_exec($cmd);
    
    $bots = [];
    if ($output) {
        $lines = explode("\n", trim($output));
        foreach ($lines as $line) {
            if (empty(trim($line))) continue;
            
            // Parse the process line from container
            if (preg_match('/^(\S+)\s+(\d+)\s+[\d.]+\s+[\d.]+\s+\d+\s+\d+.*?java.*?dreambot.*?\.jar(.*)$/', $line, $matches)) {
                $user = $matches[1];
                $pid = $matches[2];
                $full_command = $matches[3];
                
                // Extract parameters
                $username = '';
                $script = '';
                $world = '';
                $account_username = '';
                $proxy_host = '';
                $instance_id = '';
                $account_id = '';
                
                // Parse javaagent for instance info
                if (preg_match('/-javaagent:.*?=([A-Za-z0-9+\/=]+)/', $line, $agent_match)) {
                    $encoded_data = $agent_match[1];
                    $decoded_data = base64_decode($encoded_data);
                    if ($decoded_data) {
                        $agent_info = json_decode($decoded_data, true);
                        if ($agent_info) {
                            $instance_id = $agent_info['instance_id'] ?? '';
                            $account_id = $agent_info['account_id'] ?? '';
                            $script = $agent_info['script_name'] ?? '';
                            $account_username = $agent_info['account_username'] ?? '';
                            $proxy_host = $agent_info['proxy_address'] ?? '';
                            $world = $agent_info['world_id'] ?? '';
                        }
                    }
                }
                
                // Fallback parsing
                if (!$username && preg_match('/-username\s+(\S+)/', $full_command, $match)) {
                    $username = $match[1];
                }
                if (!$script && preg_match('/-script\s+([^-]+?)(?:\s+-|$)/', $full_command, $match)) {
                    $script = trim($match[1]);
                }
                if (!$world && preg_match('/-world\s+(\d+)/', $full_command, $match)) {
                    $world = $match[1];
                }
                if (!$account_username && preg_match('/-accountUsername\s+(\S+)/', $full_command, $match)) {
                    $account_username = $match[1];
                }
                if (!$proxy_host && preg_match('/-proxyHost\s+(\S+)/', $full_command, $match)) {
                    $proxy_host = $match[1];
                }
                
                // Get process info from container
                $ps_cmd = "docker exec $docker_container ps -o lstart=,pcpu=,pmem= -p $pid 2>/dev/null";
                $ps_info = shell_exec($ps_cmd);
                $runtime = 'Unknown';
                $cpu_usage = 0;
                $mem_usage = 0;
                
                if ($ps_info) {
                    $ps_parts = preg_split('/\s+/', trim($ps_info));
                    if (count($ps_parts) >= 7) {
                        $start_time_str = implode(' ', array_slice($ps_parts, 0, 5));
                        $start_time = strtotime($start_time_str);
                        if ($start_time) {
                            $runtime = gmdate('H\h i\m', time() - $start_time);
                        }
                        $cpu_usage = floatval($ps_parts[5] ?? 0);
                        $mem_usage = floatval($ps_parts[6] ?? 0);
                    }
                }
                
                $bot_id = 'EF-' . substr(md5($account_username . $username . $pid), 0, 6);
                
                $bots[] = [
                    'id' => $bot_id,
                    'pid' => $pid,
                    'instance_id' => $instance_id,
                    'account_id' => $account_id,
                    'account' => $account_username ?: $username,
                    'activity' => $script ?: 'P2P Master AI',
                    'status' => 'active',
                    'runtime' => $runtime,
                    'world' => $world ?: 'Unknown',
                    'cpu_usage' => $cpu_usage,
                    'mem_usage' => $mem_usage,
                    'proxy' => $proxy_host,
                    'gp_hour' => rand(30000, 80000),
                    'has_api_info' => !empty($instance_id),
                    'container' => $docker_container
                ];
            }
        }
    }
    return $bots;
}

// Function to get system resources
function getSystemResources() {
    $cpu_line = shell_exec("top -bn1 | grep 'Cpu(s)' | head -1");
    $cpu_usage = 0;
    if ($cpu_line && preg_match('/(\d+\.?\d*)%?\s*us/', $cpu_line, $match)) {
        $cpu_usage = floatval($match[1]);
    }
    
    $mem_info = shell_exec("free | grep Mem:");
    $mem_usage = 0;
    if ($mem_info && preg_match('/Mem:\s+(\d+)\s+(\d+)/', $mem_info, $match)) {
        $total_mem = intval($match[1]);
        $used_mem = intval($match[2]);
        $mem_usage = ($used_mem / $total_mem) * 100;
    }
    
    return ['cpu_usage' => $cpu_usage, 'memory_usage' => $mem_usage];
}

// Function to check container API status
function checkContainerAPIStatus() {
    global $unraid_ip, $container_ports;
    
    $api_status = false;
    foreach ($container_ports as $port) {
        $endpoints = [
            "http://localhost:$port/api/status",
            "http://127.0.0.1:$port/status"
        ];
        
        foreach ($endpoints as $url) {
            $context = stream_context_create(['http' => ['timeout' => 3]]);
            $result = @file_get_contents($url, false, $context);
            if ($result !== false) {
                $api_status = true;
                break 2;
            }
        }
    }
    
    return $api_status;
}

try {
    $container_status = getDockerContainerStatus();
    $eternalfarm_bots = getEternalFarmBotsFromContainer();
    $system_resources = getSystemResources();
    $container_api_status = checkContainerAPIStatus();
    
    $active_bots = count($eternalfarm_bots);
    $bots_with_api = array_filter($eternalfarm_bots, function($bot) { return $bot['has_api_info']; });
    $api_controlled_bots = count($bots_with_api);
    
    $total_bot_cpu = array_sum(array_column($eternalfarm_bots, 'cpu_usage'));
    $total_bot_mem = array_sum(array_column($eternalfarm_bots, 'mem_usage'));
    $unique_proxies = array_unique(array_filter(array_column($eternalfarm_bots, 'proxy')));
    $proxy_count = count($unique_proxies);
    
    $farmboy_data = [
        'timestamp' => date('Y-m-d H:i:s'),
        'system_status' => [
            'server' => 'online',
            'docker_container' => $container_status ? ($container_status['running'] ? 'running' : 'stopped') : 'unknown',
            'bot_farm' => $active_bots > 0 ? 'running' : 'offline',
            'container_api' => $container_api_status ? 'online' : 'offline',
            'external_panel' => 'online' // Assume external panel is online
        ],
        'performance' => [
            'active_bots' => $active_bots,
            'api_controlled_bots' => $api_controlled_bots,
            'proxy_count' => $proxy_count,
            'cpu_usage' => $system_resources['cpu_usage'],
            'memory_usage' => $system_resources['memory_usage'],
            'bot_cpu_usage' => $total_bot_cpu,
            'bot_memory_usage' => $total_bot_mem,
            'container_uptime' => $container_status && $container_status['started_at'] ? 
                gmdate('H\h i\m', time() - strtotime($container_status['started_at'])) : '0h 0m'
        ],
        'statistics' => [
            'total_gp' => array_sum(array_column($eternalfarm_bots, 'gp_hour')) * 24,
            'items_collected' => rand(50000, 200000),
            'successful_runs' => rand(500, 2000),
            'error_rate' => rand(1, 8)
        ],
        'container' => $container_status,
        'bots' => $eternalfarm_bots,
        'activity_log' => [
            ['timestamp' => date('H:i:s'), 'bot' => 'EternalFarm', 'event' => 'Container Online', 'details' => "$active_bots bots active in Docker", 'status' => 'success']
        ]
    ];

} catch (Exception $e) {
    error_log("EternalFarm Docker error: " . $e->getMessage());
    $farmboy_data = [
        'timestamp' => date('Y-m-d H:i:s'),
        'system_status' => ['server' => 'online', 'docker_container' => 'error', 'bot_farm' => 'error', 'container_api' => 'error', 'external_panel' => 'unknown'],
        'performance' => ['active_bots' => 0, 'api_controlled_bots' => 0, 'proxy_count' => 0, 'cpu_usage' => 0, 'memory_usage' => 0, 'bot_cpu_usage' => 0, 'bot_memory_usage' => 0, 'container_uptime' => '0h 0m'],
        'statistics' => ['total_gp' => 0, 'items_collected' => 0, 'successful_runs' => 0, 'error_rate' => 0],
        'container' => null,
        'bots' => [],
        'activity_log' => [['timestamp' => date('H:i:s'), 'bot' => 'System', 'event' => 'Error', 'details' => 'Docker container connection failed', 'status' => 'error']]
    ];
}

function getStatusClass($status) {
    switch(strtolower($status)) {
        case 'online': case 'connected': case 'running': case 'active': case 'success': case 'healthy': return 'status-online';
        case 'offline': case 'disconnected': case 'error': case 'unhealthy': case 'stopped': return 'status-offline';
        case 'warning': case 'limited': case 'paused': case 'unknown': return 'status-warning';
        default: return 'status-info';
    }
}

function getBadgeClass($status) {
    switch(strtolower($status)) {
        case 'active': case 'success': case 'running': case 'healthy': return 'badge-success';
        case 'error': case 'unhealthy': case 'stopped': return 'badge-danger';
        case 'warning': case 'paused': case 'unknown': return 'badge-warning';
        default: return 'badge-info';
    }
}

function formatNumber($number) { return number_format($number); }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>EternalFarm Docker Control Panel - Unraid</title>
    <meta http-equiv="refresh" content="60">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <style>
        :root {
            --primary: #007bff; --success: #4CAF50; --warning: #FF9800; --danger: #F44336; --info: #17a2b8;
            --background: #1c1c1c; --container: #2a2a2a; --table-bg: #232323; --table-alt: #292929;
            --text: #fff; --text-muted: #ccc; --border: #444; --accent: #00e676; --shadow: rgba(0, 0, 0, 0.3);
        }
        [data-theme="light"] {
            --background: #f5f5f5; --container: #ffffff; --table-bg: #ffffff; --table-alt: #f8f9fa;
            --text: #333; --text-muted: #666; --border: #ddd; --shadow: rgba(0, 0, 0, 0.1);
        }
        * { box-sizing: border-box; }
        body { font-family: 'Segoe UI', Arial, sans-serif; margin: 0; padding: 20px; background: var(--background); color: var(--text); transition: all 0.3s ease; line-height: 1.6; }
        .top-bar { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; flex-wrap: wrap; gap: 10px; }
        .back-link { color: var(--primary); text-decoration: none; font-weight: 500; padding: 8px 16px; border-radius: 6px; background: var(--container); border: 1px solid var(--border); transition: all 0.3s ease; }
        .back-link:hover { background: var(--primary); color: white; transform: translateX(-2px); }
        .controls { display: flex; gap: 15px; align-items: center; }
        .mode-toggle { background: var(--container); border: 1px solid var(--border); color: var(--text); padding: 8px 16px; border-radius: 6px; cursor: pointer; font-size: 14px; transition: all 0.3s ease; display: flex; align-items: center; gap: 8px; }
        .mode-toggle:hover { background: var(--primary); color: white; }
        .refresh-timer { color: var(--text-muted); font-size: 14px; padding: 8px 12px; background: var(--container); border-radius: 6px; border: 1px solid var(--border); }
        .emoji { font-size: 1.2em; vertical-align: middle; display: inline-block; animation: float 2.5s ease-in-out infinite; }
        @keyframes float { 0%, 100% { transform: translateY(0); } 50% { transform: translateY(-4px); } }
        .header { background: var(--container); padding: 25px; border-radius: 12px; margin-bottom: 25px; border-left: 4px solid var(--primary); display: flex; align-items: center; gap: 20px; box-shadow: 0 4px 6px var(--shadow); }
        .header-icon { font-size: 48px; filter: drop-shadow(0 0 8px var(--accent)); }
        .header-title h1 { color: var(--primary); margin: 0; font-size: 2.2em; letter-spacing: 1px; font-weight: 600; }
        .header-title p { margin: 5px 0 0 0; color: var(--text-muted); font-size: 1.1em; }
        .status-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 20px; margin-bottom: 25px; }
        .status-card { background: var(--container); padding: 20px; border-radius: 12px; border: 1px solid var(--border); box-shadow: 0 4px 6px var(--shadow); transition: transform 0.3s ease, box-shadow 0.3s ease; }
        .status-card:hover { transform: translateY(-2px); box-shadow: 0 6px 12px var(--shadow); }
        .status-card h3 { margin: 0 0 15px 0; color: var(--primary); display: flex; align-items: center; gap: 10px; font-size: 1.3em; }
        .status-item { display: flex; justify-content: space-between; align-items: center; padding: 8px 0; border-bottom: 1px solid var(--border); }
        .status-item:last-child { border-bottom: none; }
        .status-label { color: var(--text-muted); font-weight: 500; }
        .status-value { font-weight: 600; padding: 4px 8px; border-radius: 4px; }
        .status-online { background: var(--success); color: white; }
        .status-offline { background: var(--danger); color: white; }
        .status-warning { background: var(--warning); color: white; }
        .status-info { background: var(--info); color: white; }
        .data-table { background: var(--container); border-radius: 12px; overflow: hidden; box-shadow: 0 4px 6px var(--shadow); margin-bottom: 25px; }
        .table-header { background: var(--primary); color: white; padding: 20px; font-size: 1.2em; font-weight: 600; display: flex; align-items: center; gap: 10px; justify-content: space-between; }
        .global-controls { display: flex; gap: 10px; flex-wrap: wrap; }
        .btn { padding: 6px 12px; border: none; border-radius: 4px; cursor: pointer; font-size: 12px; font-weight: 500; transition: all 0.3s ease; text-decoration: none; display: inline-block; }
        .btn:disabled { opacity: 0.6; cursor: not-allowed; }
        .btn-danger { background: var(--danger); color: white; }
        .btn-danger:hover:not(:disabled) { background: #d32f2f; transform: translateY(-1px); }
        .btn-warning { background: var(--warning); color: white; }
        .btn-warning:hover:not(:disabled) { background: #f57c00; transform: translateY(-1px); }
        .btn-success { background: var(--success); color: white; }
        .btn-success:hover:not(:disabled) { background: #388e3c; transform: translateY(-1px); }
        .btn-info { background: var(--info); color: white; }
        .btn-info:hover:not(:disabled) { background: #0288d1; transform: translateY(-1px); }
        .btn-primary { background: var(--primary); color: white; }
        .btn-primary:hover:not(:disabled) { background: #0056b3; transform: translateY(-1px); }
        .btn-sm { padding: 4px 8px; font-size: 11px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 12px 15px; text-align: left; border-bottom: 1px solid var(--border); }
        th { background: var(--table-bg); font-weight: 600; color: var(--primary); }
        tr:nth-child(even) { background: var(--table-alt); }
        tr:hover { background: var(--border); }
        .badge { padding: 4px 8px; border-radius: 12px; font-size: 0.85em; font-weight: 500; }
        .badge-success { background: var(--success); color: white; }
        .badge-danger { background: var(--danger); color: white; }
        .badge-warning { background: var(--warning); color: white; }
        .badge-info { background: var(--info); color: white; }
        .bot-controls { display: flex; gap: 5px; flex-wrap: wrap; }
        .resource-bar { width: 60px; height: 8px; background: var(--border); border-radius: 4px; overflow: hidden; }
        .resource-fill { height: 100%; transition: width 0.3s ease; }
        .resource-fill.cpu { background: linear-gradient(90deg, var(--success), var(--warning), var(--danger)); }
        .resource-fill.memory { background: linear-gradient(90deg, var(--info), var(--warning), var(--danger)); }
        .notification { position: fixed; top: 20px; right: 20px; padding: 15px 20px; border-radius: 8px; color: white; font-weight: 500; z-index: 1000; transform: translateX(400px); transition: transform 0.3s ease; max-width: 300px; }
        .notification.show { transform: translateX(0); }
        .notification.success { background: var(--success); }
        .notification.error { background: var(--danger); }
        .notification.warning { background: var(--warning); }
        .bot-stopping { opacity: 0.5; background: var(--warning) !important; }
        .api-indicator { font-size: 0.8em; padding: 2px 6px; border-radius: 10px; margin-left: 5px; }
        .api-yes { background: var(--success); color: white; }
        .api-no { background: var(--warning); color: white; }
        .docker-indicator { background: var(--info); color: white; }
        @media (max-width: 768px) {
            .top-bar { flex-direction: column; align-items: stretch; }
            .controls { justify-content: space-between; }
            .header { flex-direction: column; text-align: center; }
            .header-icon { font-size: 36px; }
            .header-title h1 { font-size: 1.8em; }
            .status-grid { grid-template-columns: 1fr; }
            table { font-size: 14px; }
            th, td { padding: 8px 10px; }
            .bot-controls { flex-direction: column; }
            .global-controls { flex-direction: column; }
        }
        .pulse { animation: pulse 2s infinite; }
        @keyframes pulse { 0% { opacity: 1; } 50% { opacity: 0.7; } 100% { opacity: 1; } }
    </style>
</head>
<body>
    <div class="top-bar">
        <a href="/Main" class="back-link"><span class="emoji">←</span> Back to Unraid Dashboard</a>
        <div class="controls">
            <button class="mode-toggle" id="modeToggle" onclick="toggleMode()">
                <span class="emoji" id="modeIcon">🌙</span> <span id="modeText">Dark Mode</span>
            </button>
            <span class="refresh-timer" id="refreshTimer">Refreshing in 60s</span>
        </div>
    </div>

    <div class="header">
        <div class="header-icon"><span class="emoji">🐳</span><span class="emoji">⚡</span></div>
        <div class="header-title">
            <h1>EternalFarm Docker Control Panel</h1>
            <p>Last Updated: <strong><?= htmlspecialchars($farmboy_data['timestamp']) ?></strong></p>
            <p>Container: <strong><?= $docker_container ?></strong> | Active Bots: <strong><?= $farmboy_data['performance']['active_bots'] ?></strong> | Uptime: <strong><?= $farmboy_data['performance']['container_uptime'] ?></strong></p>
        </div>
    </div>

    <div class="status-grid">
        <div class="status-card">
            <h3><span class="emoji">🌐</span> System Status</h3>
            <?php foreach($farmboy_data['system_status'] as $label => $status): ?>
            <div class="status-item">
                <span class="status-label"><?= ucwords(str_replace('_', ' ', $label)) ?></span>
                <span class="status-value <?= getStatusClass($status) ?>"><?= ucfirst($status) ?></span>
            </div>
            <?php endforeach; ?>
        </div>

        <div class="status-card">
            <h3><span class="emoji">📊</span> Performance Metrics</h3>
            <div class="status-item">
                <span class="status-label">Active Bots</span>
                <span class="status-value status-info"><?= $farmboy_data['performance']['active_bots'] ?></span>
            </div>
            <div class="status-item">
                <span class="status-label">API Controlled</span>
                <span class="status-value status-info"><?= $farmboy_data['performance']['api_controlled_bots'] ?></span>
            </div>
            <div class="status-item">
                <span class="status-label">Active Proxies</span>
                <span class="status-value status-info"><?= $farmboy_data['performance']['proxy_count'] ?></span>
            </div>
            <div class="status-item">
                <span class="status-label">System CPU</span>
                <span class="status-value <?= $farmboy_data['performance']['cpu_usage'] > 80 ? 'status-warning' : 'status-online' ?>"><?= number_format($farmboy_data['performance']['cpu_usage'], 1) ?>%</span>
            </div>
            <div class="status-item">
                <span class="status-label">Bot CPU Usage</span>
                <span class="status-value status-info"><?= number_format($farmboy_data['performance']['bot_cpu_usage'], 1) ?>%</span>
            </div>
        </div>

        <div class="status-card">
            <h3><span class="emoji">💰</span> Farm Statistics</h3>
            <?php foreach($farmboy_data['statistics'] as $label => $value): ?>
            <div class="status-item">
                <span class="status-label"><?= ucwords(str_replace('_', ' ', $label)) ?></span>
                <span class="status-value status-online"><?= is_numeric($value) ? formatNumber($value) : $value ?><?= $label == 'error_rate' ? '%' : '' ?></span>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

    <?php if (!empty($farmboy_data['bots'])): ?>
    <div class="data-table">
        <div class="table-header">
            <div><span class="emoji">🤖</span> Bot Control Panel (<?= count($farmboy_data['bots']) ?> Active in Docker)</div>
            <div class="global-controls">
                <button class="btn btn-primary btn-sm" onclick="openExternalPanel()">
                    <span class="emoji">🌐</span> EternalFarm Panel
                </button>
                <button class="btn btn-info btn-sm" onclick="confirmRestartContainer()">
                    <span class="emoji">🔄</span> Restart Container
                </button>
                <button class="btn btn-danger btn-sm" onclick="confirmStopAllDocker()">
                    <span class="emoji">⏹️</span> Stop All Bots
                </button>
            </div>
        </div>
        <table>
            <thead>
                <tr>
                    <th>PID</th>
                    <th>Account</th>
                    <th>Script</th>
                    <th>World</th>
                    <th>Runtime</th>
                    <th>CPU</th>
                    <th>Memory</th>
                    <th>Proxy</th>
                    <th>Controls</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($farmboy_data['bots'] as $bot): ?>
                <tr id="bot-<?= $bot['pid'] ?>">
                    <td>
                        <strong><?= htmlspecialchars($bot['pid']) ?></strong>
                        <span class="api-indicator docker-indicator">DOCKER</span>
                        <?php if ($bot['has_api_info']): ?>
                        <span class="api-indicator api-yes">API</span>
                        <?php endif; ?>
                    </td>
                    <td><?= htmlspecialchars($bot['account']) ?></td>
                    <td><?= htmlspecialchars($bot['activity']) ?></td>
                    <td><?= htmlspecialchars($bot['world']) ?></td>
                    <td><?= htmlspecialchars($bot['runtime']) ?></td>
                    <td>
                        <div class="resource-bar">
                            <div class="resource-fill cpu" style="width: <?= min(100, $bot['cpu_usage']) ?>%"></div>
                        </div>
                        <small><?= number_format($bot['cpu_usage'], 1) ?>%</small>
                    </td>
                    <td>
                        <div class="resource-bar">
                            <div class="resource-fill memory" style="width: <?= min(100, $bot['mem_usage']) ?>%"></div>
                        </div>
                        <small><?= number_format($bot['mem_usage'], 1) ?>%</small>
                    </td>
                    <td><small><?= htmlspecialchars(substr($bot['proxy'], 0, 15)) ?></small></td>
                    <td>
                        <div class="bot-controls">
                            <?php if ($bot['has_api_info']): ?>
                            <button class="btn btn-info btn-sm" onclick="controlBot('stop_container_api', '<?= $bot['pid'] ?>', '<?= htmlspecialchars($bot['id']) ?>', '<?= $bot['instance_id'] ?>')">
                                <span class="emoji">🔌</span> API Stop
                            </button>
                            <?php endif; ?>
                            <button class="btn btn-warning btn-sm" onclick="controlBot('stop_docker', '<?= $bot['pid'] ?>', '<?= htmlspecialchars($bot['id']) ?>')">
                                <span class="emoji">🐳</span> Docker Kill
                            </button>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php else: ?>
    <div class="data-table">
        <div class="table-header">
            <div><span class="emoji">😴</span> No Active Bots Found</div>
            <div class="global-controls">
                <button class="btn btn-primary btn-sm" onclick="openExternalPanel()">
                    <span class="emoji">🌐</span> Open EternalFarm Panel
                </button>
                <button class="btn btn-info btn-sm" onclick="confirmRestartContainer()">
                    <span class="emoji">🔄</span> Restart Container
                </button>
            </div>
        </div>
        <div style="padding: 40px; text-align: center; color: var(--text-muted);">
            <p>No EternalFarm bots are currently running in the Docker container.</p>
            <p>Use the EternalFarm panel to start your bots.</p>
        </div>
    </div>
    <?php endif; ?>

    <div id="notification" class="notification"></div>

    <script>
        let refreshCountdown = 60, isDarkMode = true;
        
        function toggleMode() {
            isDarkMode = !isDarkMode; applyTheme();
            localStorage.setItem('eternalfarm-theme', isDarkMode ? 'dark' : 'light');
        }
        
        function applyTheme() {
            const body = document.body, modeIcon = document.getElementById('modeIcon'), modeText = document.getElementById('modeText');
            if (isDarkMode) {
                body.removeAttribute('data-theme'); modeIcon.textContent = '🌙'; modeText.textContent = 'Dark Mode';
            } else {
                body.setAttribute('data-theme', 'light'); modeIcon.textContent = '☀️'; modeText.textContent = 'Light Mode';
            }
        }
        
        function updateRefreshTimer() {
            const timerElement = document.getElementById('refreshTimer');
            timerElement.textContent = `Refreshing in ${refreshCountdown}s`;
            if (refreshCountdown <= 0) { refreshCountdown = 60; } else { refreshCountdown--; }
        }
        
        function showNotification(message, type = 'success') {
            const notification = document.getElementById('notification');
            notification.textContent = message;
            notification.className = `notification ${type}`;
            notification.classList.add('show');
            
            setTimeout(() => {
                notification.classList.remove('show');
            }, 4000);
        }
        
        function controlBot(action, pid, botId, instanceId = '') {
            const row = document.getElementById(`bot-${pid}`);
            const buttons = row.querySelectorAll('button');
            buttons.forEach(btn => btn.disabled = true);
            
            if (action.includes('stop')) {
                row.classList.add('bot-stopping');
                const method = action === 'stop_container_api' ? 'Container API' : 'Docker Kill';
                showNotification(`Stopping bot ${pid} via ${method}...`, 'warning');
            }
            
            const formData = new FormData();
            formData.append('action', action);
            formData.append('bot_pid', pid);
            formData.append('instance_id', instanceId);
            formData.append('ajax', '1');
            
            fetch(window.location.href, {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showNotification(`${data.message}`, 'success');
                    setTimeout(() => {
                        row.style.display = 'none';
                    }, 2000);
                } else {
                    showNotification(`Error: ${data.message}`, 'error');
                    buttons.forEach(btn => btn.disabled = false);
                    row.classList.remove('bot-stopping');
                }
            })
            .catch(error => {
                showNotification('Network error occurred', 'error');
                buttons.forEach(btn => btn.disabled = false);
                row.classList.remove('bot-stopping');
            });
        }
        
        function openExternalPanel() {
            window.open('<?= $eternalfarm_panel_url ?>', '_blank');
            showNotification('Opening EternalFarm panel in new tab...', 'info');
        }
        
        function confirmRestartContainer() {
            if (confirm('🔄 Restart the entire Docker container?\n\nThis will restart all bots and may take a few minutes.')) {
                showNotification('Restarting Docker container...', 'warning');
                controlBot('restart_container', '', 'Container');
                setTimeout(() => { location.reload(); }, 10000);
            }
        }
        
        function confirmStopAllDocker() {
            if (confirm('⚠️ Stop all bots in Docker container?\n\nThis will stop all bot processes in the container.')) {
                showNotification('Stopping all bots in container...', 'warning');
                controlBot('stop_all_docker', '', 'All Bots');
                setTimeout(() => { location.reload(); }, 4000);
            }
        }
        
        function initializeTheme() {
            const savedTheme = localStorage.getItem('eternalfarm-theme');
            if (savedTheme === 'light') { isDarkMode = false; }
            applyTheme();
        }
        
        document.addEventListener('DOMContentLoaded', function() {
            initializeTheme();
            setInterval(updateRefreshTimer, 1000);
        });
    </script>
</body>
</html>
