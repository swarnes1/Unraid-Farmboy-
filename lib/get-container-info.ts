// Container data types
export interface ContainerInfo {
  running: boolean
  status: string
  health: string
  started_at: string | null
  image: string
}

export interface BotInfo {
  id: string
  account: string
  activity: string
  status: string
  runtime: string
  gp_hour: number
}

export interface LogEntry {
  timestamp: string
  bot: string
  event: string
  details: string
  status: string
}

export interface FarmboyData {
  timestamp: string
  system_status: {
    server: string
    bot_farm: string
    database: string
    api: string
  }
  performance: {
    active_bots: number
    total_bots: number
    active_containers: number
    total_containers: number
    cpu_usage: number
    memory_usage: number
    uptime: string
  }
  statistics: {
    total_gp: number
    items_collected: number
    successful_runs: number
    error_rate: number
  }
  containers: Record<string, ContainerInfo>
  bots: BotInfo[]
  activity_log: LogEntry[]
}

// This would normally be a server-side function that calls Docker APIs
// For now, we'll return mock data similar to the PHP version
export async function getContainerInfo(): Promise<FarmboyData> {
  // In a real implementation, this would make API calls to your server
  // which would then execute the Docker commands

  // Mock data based on the PHP implementation
  return {
    timestamp: new Date().toISOString(),
    system_status: {
      server: "online",
      bot_farm: "running",
      database: "connected",
      api: "online",
    },
    performance: {
      active_bots: 3,
      total_bots: 5,
      active_containers: 4,
      total_containers: 5,
      cpu_usage: 45,
      memory_usage: 60,
      uptime: "5h 23m",
    },
    statistics: {
      total_gp: 456789,
      items_collected: 34567,
      successful_runs: 234,
      error_rate: 2,
    },
    containers: {
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
    },
    bots: [
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
      { id: "FB-003", account: "FarmBot_Gamma", activity: "Fishing", status: "paused", runtime: "0h 05m", gp_hour: 0 },
    ],
    activity_log: [
      {
        timestamp: "12:45:30",
        bot: "farmboy",
        event: "Container Started",
        details: "Main container online",
        status: "success",
      },
      { timestamp: "12:44:15", bot: "metrics", event: "Data Collection", details: "Metrics updated", status: "info" },
      {
        timestamp: "12:40:22",
        bot: "FB-001",
        event: "Bot Started",
        details: "Woodcutting script initialized",
        status: "success",
      },
      {
        timestamp: "12:35:18",
        bot: "FB-002",
        event: "Level Up",
        details: "Mining level increased to 75",
        status: "success",
      },
      { timestamp: "12:30:05", bot: "FB-003", event: "Error", details: "Connection timeout", status: "error" },
      {
        timestamp: "12:25:47",
        bot: "manager",
        event: "Stopped",
        details: "Manager container stopped",
        status: "warning",
      },
      {
        timestamp: "12:20:33",
        bot: "numa-balancer",
        event: "Resource Allocation",
        details: "CPU cores rebalanced",
        status: "info",
      },
    ],
  }
}

// Helper functions from the PHP code
export function getStatusClass(status: string): string {
  switch (status.toLowerCase()) {
    case "online":
    case "connected":
    case "running":
    case "active":
    case "success":
    case "healthy":
      return "bg-green-500 text-white"
    case "offline":
    case "disconnected":
    case "error":
    case "unhealthy":
      return "bg-red-500 text-white"
    case "warning":
    case "limited":
    case "paused":
      return "bg-yellow-500 text-white"
    default:
      return "bg-blue-500 text-white"
  }
}

export function getBadgeClass(status: string): string {
  switch (status.toLowerCase()) {
    case "active":
    case "success":
    case "running":
    case "healthy":
      return "bg-green-500 text-white"
    case "error":
    case "unhealthy":
      return "bg-red-500 text-white"
    case "warning":
    case "paused":
      return "bg-yellow-500 text-white"
    default:
      return "bg-blue-500 text-white"
  }
}

export function formatNumber(number: number): string {
  return new Intl.NumberFormat().format(number)
}
