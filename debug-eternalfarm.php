<?php
// Debug script to see what processes are running
echo "<h2>EternalFarm Process Debug</h2>";

// Check for Java processes
echo "<h3>All Java Processes:</h3>";
$java_processes = shell_exec("ps aux | grep java | grep -v grep");
echo "<pre>" . htmlspecialchars($java_processes) . "</pre>";

// Check for DreamBot processes
echo "<h3>DreamBot Processes:</h3>";
$dreambot_processes = shell_exec("ps aux | grep dreambot | grep -v grep");
echo "<pre>" . htmlspecialchars($dreambot_processes) . "</pre>";

// Check for any EternalFarm related processes
echo "<h3>EternalFarm Processes:</h3>";
$eternal_processes = shell_exec("ps aux | grep -i eternal | grep -v grep");
echo "<pre>" . htmlspecialchars($eternal_processes) . "</pre>";

// Check what's in the EternalFarm directory
echo "<h3>EternalFarm Directory Contents:</h3>";
$eternalfarm_path = '/root/EternalFarm';
if (is_dir($eternalfarm_path)) {
    $dir_contents = shell_exec("ls -la $eternalfarm_path");
    echo "<pre>" . htmlspecialchars($dir_contents) . "</pre>";
} else {
    echo "Directory $eternalfarm_path not found<br>";
}

// Check for any .jar files
echo "<h3>JAR Files in EternalFarm:</h3>";
$jar_files = shell_exec("find $eternalfarm_path -name '*.jar' 2>/dev/null");
echo "<pre>" . htmlspecialchars($jar_files) . "</pre>";

// Check running processes with full command line
echo "<h3>All Processes (full command):</h3>";
$all_processes = shell_exec("ps auxww | grep -E '(java|dreambot|eternal)' | grep -v grep");
echo "<pre>" . htmlspecialchars($all_processes) . "</pre>";
?>
