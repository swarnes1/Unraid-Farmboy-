"use server"

import type { FarmboyData, ContainerInfo, BotInfo, LogEntry } from "@/lib/get-container-info"

// Your Farmboy container configuration
const MAIN_CONTAINER = "farmboy"
const CONTAINER_NAMES = ["farmboy", "metrics", "numa-balancer", "monitor", "manager"]

// Check if we're in a server environment with access to Node.js APIs
const isServerEnvironment = typeof process !== "undefined" && process.versions?.node

// Mock data for preview/development
const mockContainerData: Record<string, ContainerInfo> = {
  farmboy: {
    running: true,
    status: "running",
    health: "healthy",
    started_at: new Date(Date.now() - 5 * 60 * 60 * 1000).toISOString(),
    image: "farmboy/main:latest",
  },
  metrics: {
    running: true,
    status: "running",
    health: "healthy",
    started_at: new Date(Date.now() - 4 * 60 * 60 * 1000).toISOString(),
    image: "farmboy/metrics:latest",
  },
  "numa-balancer": {
    running: true,
    status: "running",
    health: "healthy",
    started_at: new Date(Date.now() - 6 * 60 * 60 * 1000).toISOString(),
    image: "farmboy/numa-balancer:latest",
  },
  monitor: {
    running: true,
    status: "running",
    health: "healthy",
    started_at: new Date(Date.now() - 3 * 60 * 60 * 1000).toISOString(),
    image: "farmboy/monitor:latest",
  },
  manager: {
    running: false,
    status: "exited",
    health: "unhealthy",
    started_at: null,
    image: "farmboy/manager:latest",
  },
}

const mockBotData: BotInfo[] = [
  {
    id: "FB-001",
    account: "FarmBot_Alpha",
    activity: "Woodcutting",
    status: "active",
    runtime: "2h 15m",
    gp_hour: 45000,
  },
  {
    id: "FB-002",
    account: "FarmBot_Beta",
    activity: "Mining",
    status: "active",
    runtime: "1h 42m",
    gp_hour: 52000,
  },
  {
    id: "FB-003",
    account: "FarmBot_Gamma",
    activity: "Fishing",
    status: "paused",
    runtime: "0h 05m",
    gp_hour: 0,
  },
  {
    id: "FB-004",
    account: "FarmBot_Delta",
    activity: "Combat",
    status: "active",
    runtime: "3h 30m",
    gp_hour: 38000,
  },
  {
    id: "FB-005",
    account: "FarmBot_Echo",
    activity: "Crafting",
    status: "error",
    runtime: "0h 02m",
    gp_hour: 0,
  },
]

const mockActivityLog: LogEntry[] = [
  {
    timestamp: new Date().toLocaleTimeString(),
    bot: "farmboy",
    event: "Container Started",
    details: "Main container online",
    status: "success",
  },
  {
    timestamp: new Date(Date.now() - 60000).toLocaleTimeString(),
    bot: "metrics",
    event: "Data Collection",
    details: "Metrics updated successfully",
    status: "info",
  },
  {
    timestamp: new Date(Date.now() - 120000).toLocaleTimeString(),
    bot: "FB-001",
    event: "Bot Started",
    details: "Woodcutting script initialized",
    status: "success",
  },
  {
    timestamp: new Date(Date.now() - 180000).toLocaleTimeString(),
    bot: "FB-002",
    event: "Level Up",
    details: "Mining level increased to 75",
    status: "success",
  },
  {
    timestamp: new Date(Date.now() - 240000).toLocaleTimeString(),
    bot: "FB-005",
    event: "Error",
    details: "Connection timeout - retrying",
    status: "error",
  },
  {
    timestamp: new Date(Date.now() - 300000).toLocaleTimeString(),
    bot: "manager",
    event: "Stopped",
    details: "Manager container stopped for maintenance",
    status: "warning",
  },
  {
    timestamp: new Date(Date.now() - 360000).toLocaleTimeString(),
    bot: "numa-balancer",
    event: "Resource Allocation",
    details: "CPU cores rebalanced for optimal performance",
    status: "info",
  },
]

// Function to execute Docker commands (only works in real server environment)
async function executeDockerCommand(command: string): Promise<string> {
  if (!isServerEnvironment) {
    throw new Error("Docker commands not available in preview environment")
  }

  try {
    // Dynamic import to avoid issues in browser environment
    const { exec } = await import("child_process")
    const { promisify } = await import("util")
    const execPromise = promisify(exec)

    const { stdout } = await execPromise(command)
    return stdout
  } catch (error) {
    console.error(`Error executing Docker command: ${command}`, error)
    throw error
  }
}

// Function to get real container info
async function getRealContainerInfo(containerName: string): Promise<ContainerInfo | null> {
  try {
    const output = await executeDockerCommand(`docker inspect ${containerName} 2>/dev/null`)

    if (output) {
      const containerInfo = JSON.parse(output)
      if (containerInfo && containerInfo[0]) {
        const info = containerInfo[0]
        return {
          running: info.State?.Running || false,
          status: info.State?.Status || "unknown",
          health: info.State?.Health?.Status || "unknown",
          started_at: info.State?.StartedAt || null,
          image: info.Config?.Image || "unknown",
        }
      }
    }
    return null
  } catch (error) {
    console.error(`Error inspecting container ${containerName}:`, error)
    return null
  }
}

// Function to get real system resource usage
async function getRealSystemResourceUsage(): Promise<{ cpu: number; memory: number }> {
  try {
    // Get CPU usage
    const cpuOutput = await executeDockerCommand("top -bn1 | grep 'Cpu(s)' | awk '{print $2 + $4}'")
    const cpuUsage = Number.parseFloat(cpuOutput) || 0

    // Get memory usage
    const memOutput = await executeDockerCommand("free | grep Mem | awk '{print $3/$2 * 100.0}'")
    const memoryUsage = Number.parseFloat(memOutput) || 0

    return {
      cpu: Math.round(cpuUsage),
      memory: Math.round(memoryUsage),
    }
  } catch (error) {
    console.error("Error getting system resource usage:", error)
    return { cpu: 0, memory: 0 }
  }
}

// Function to get real container logs
async function getRealContainerLogs(containerName: string, lines = 20): Promise<LogEntry[]> {
  try {
    const output = await executeDockerCommand(`docker logs --tail ${lines} ${containerName} 2>/dev/null`)

    if (output) {
      const logLines = output.trim().split("\n")
      const logs: LogEntry[] = []

      for (const line of logLines) {
        if (!line.trim()) continue

        // Try different log formats
        const logMatch = line.match(
          /(\d{4}-\d{2}-\d{2}[T\s]\d{2}:\d{2}:\d{2}).*?(\w+).*?(started|stopped|error|success|login|logout).*?(.*)/,
        )

        if (logMatch) {
          logs.push({
            timestamp: new Date(logMatch[1]).toLocaleTimeString(),
            bot: logMatch[2],
            event: logMatch[3].charAt(0).toUpperCase() + logMatch[3].slice(1),
            details: logMatch[4].trim(),
            status: logMatch[3],
          })
        } else {
          // Fallback for unstructured logs
          logs.push({
            timestamp: new Date().toLocaleTimeString(),
            bot: containerName,
            event: "Log Entry",
            details: line.substring(0, 100),
            status: "info",
          })
        }
      }

      return logs.slice(0, 10).reverse()
    }

    return []
  } catch (error) {
    console.error(`Error getting logs for container ${containerName}:`, error)
    return []
  }
}

// Main function to fetch container data
export async function fetchContainerData(): Promise<FarmboyData> {
  try {
    let containersStatus: Record<string, ContainerInfo> = {}
    let resourceUsage = { cpu: 0, memory: 0 }
    let botsData: BotInfo[] = []
    let allLogs: LogEntry[] = []

    if (isServerEnvironment) {
      // Real server environment - execute actual Docker commands
      console.log("Fetching real container data...")

      // Get container statuses
      for (const containerName of CONTAINER_NAMES) {
        const info = await getRealContainerInfo(containerName)
        if (info) {
          containersStatus[containerName] = info
        }
      }

      // Get system resource usage
      resourceUsage = await getRealSystemResourceUsage()

      // Get logs from running containers
      for (const containerName of CONTAINER_NAMES) {
        if (containersStatus[containerName]?.running) {
          const containerLogs = await getRealContainerLogs(containerName, 5)
          allLogs.push(...containerLogs)
        }
      }

      // Try to read bot data from containers (simplified for now)
      // In a real implementation, you'd read from actual data files
      botsData = mockBotData // Using mock data for now, replace with real data reading
    } else {
      // Preview/development environment - use mock data
      console.log("Using mock data for preview environment...")
      containersStatus = mockContainerData
      resourceUsage = {
        cpu: Math.floor(Math.random() * 60) + 20, // Random between 20-80
        memory: Math.floor(Math.random() * 50) + 30, // Random between 30-80
      }
      botsData = mockBotData
      allLogs = mockActivityLog
    }

    // Count running containers
    const runningContainers = Object.values(containersStatus).filter((container) => container.running)
    const totalContainers = Object.keys(containersStatus).length
    const activeContainers = runningContainers.length

    // Calculate bot statistics
    let activeBots = 0
    const totalBots = botsData.length

    for (const bot of botsData) {
      if (["active", "running", "online"].includes(bot.status.toLowerCase())) {
        activeBots++
      }
    }

    // Calculate uptime for main container
    let uptime = "0h 0m"
    if (containersStatus[MAIN_CONTAINER]?.started_at) {
      const startTime = new Date(containersStatus[MAIN_CONTAINER].started_at)
      const uptimeMs = Date.now() - startTime.getTime()
      const uptimeHours = Math.floor(uptimeMs / (1000 * 60 * 60))
      const uptimeMinutes = Math.floor((uptimeMs % (1000 * 60 * 60)) / (1000 * 60))
      uptime = `${uptimeHours}h ${uptimeMinutes}m`
    }

    // Calculate farm statistics (using dynamic values for demo)
    const baseGP = 456789
    const baseItems = 34567
    const baseRuns = 234

    return {
      timestamp: new Date().toISOString(),
      system_status: {
        server: "online",
        bot_farm: activeContainers > 0 ? "running" : "offline",
        database: activeContainers > 0 ? "connected" : "offline",
        api: activeContainers > 0 ? "online" : "limited",
      },
      performance: {
        active_bots: activeBots,
        total_bots: totalBots,
        active_containers: activeContainers,
        total_containers: totalContainers,
        cpu_usage: resourceUsage.cpu,
        memory_usage: resourceUsage.memory,
        uptime,
      },
      statistics: {
        total_gp: baseGP + Math.floor(Math.random() * 100000),
        items_collected: baseItems + Math.floor(Math.random() * 10000),
        successful_runs: baseRuns + Math.floor(Math.random() * 50),
        error_rate: Math.floor(Math.random() * 5) + 1,
      },
      containers: containersStatus,
      bots: botsData,
      activity_log: allLogs.length > 0 ? allLogs.slice(0, 10) : mockActivityLog.slice(0, 10),
    }
  } catch (error) {
    console.error("Error fetching container data:", error)

    // Return fallback data in case of error
    return {
      timestamp: new Date().toISOString(),
      system_status: {
        server: "online",
        bot_farm: "error",
        database: "error",
        api: "error",
      },
      performance: {
        active_bots: 0,
        total_bots: 0,
        active_containers: 0,
        total_containers: 5,
        cpu_usage: 0,
        memory_usage: 0,
        uptime: "0h 0m",
      },
      statistics: {
        total_gp: 0,
        items_collected: 0,
        successful_runs: 0,
        error_rate: 0,
      },
      containers: {},
      bots: [],
      activity_log: [
        {
          timestamp: new Date().toLocaleTimeString(),
          bot: "System",
          event: "Error",
          details: "Container connection failed",
          status: "error",
        },
      ],
    }
  }
}
