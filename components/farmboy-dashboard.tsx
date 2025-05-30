"use client"

import { useState, useEffect } from "react"
import { Card, CardContent } from "@/components/ui/card"
import { Button } from "@/components/ui/button"
import { type FarmboyData, getStatusClass, getBadgeClass, formatNumber } from "@/lib/get-container-info"
import { Server, Bot, Activity, Cpu, BarChart, Container, RefreshCw, ArrowLeft, Sun, Moon } from "lucide-react"
import { fetchContainerData } from "@/app/actions/container-actions"

interface FarmboyDashboardProps {
  data: FarmboyData
}

export function FarmboyDashboard({ data: initialData }: FarmboyDashboardProps) {
  const [data, setData] = useState<FarmboyData>(initialData)
  const [refreshCountdown, setRefreshCountdown] = useState(30)
  const [isDarkMode, setIsDarkMode] = useState(true)
  const [isRefreshing, setIsRefreshing] = useState(false)

  useEffect(() => {
    // Load theme preference from localStorage
    const savedTheme = localStorage.getItem("farmboy-theme")
    if (savedTheme === "light") {
      setIsDarkMode(false)
      document.documentElement.classList.remove("dark")
    } else {
      document.documentElement.classList.add("dark")
    }

    // Set up refresh countdown
    const timer = setInterval(() => {
      setRefreshCountdown((prev) => {
        if (prev <= 1) {
          // Refresh data when countdown reaches 0
          refreshData()
          return 30
        }
        return prev - 1
      })
    }, 1000)

    return () => clearInterval(timer)
  }, [])

  const refreshData = async () => {
    try {
      setIsRefreshing(true)
      // Fetch real data from the server action
      const freshData = await fetchContainerData()
      setData(freshData)
    } catch (error) {
      console.error("Failed to refresh data:", error)
    } finally {
      setIsRefreshing(false)
    }
  }

  const toggleMode = () => {
    const newMode = !isDarkMode
    setIsDarkMode(newMode)
    localStorage.setItem("farmboy-theme", newMode ? "dark" : "light")

    if (newMode) {
      document.documentElement.classList.add("dark")
    } else {
      document.documentElement.classList.remove("dark")
    }
  }

  const handleManualRefresh = () => {
    setRefreshCountdown(30)
    refreshData()
  }

  return (
    <div className="space-y-6">
      <div className="flex justify-between items-center flex-wrap gap-4">
        <Button variant="outline" asChild>
          <a href="/" className="flex items-center gap-2">
            <ArrowLeft size={16} />
            Back to Unraid Dashboard
          </a>
        </Button>

        <div className="flex items-center gap-4">
          <Button variant="outline" onClick={toggleMode}>
            {isDarkMode ? <Sun size={16} /> : <Moon size={16} />}
            <span className="ml-2">{isDarkMode ? "Light Mode" : "Dark Mode"}</span>
          </Button>

          <Button
            variant="outline"
            onClick={handleManualRefresh}
            disabled={isRefreshing}
            className="flex items-center gap-2"
          >
            <RefreshCw size={16} className={isRefreshing ? "animate-spin" : ""} />
            Refresh Now
          </Button>

          <div className="bg-muted text-muted-foreground px-3 py-1 rounded-md text-sm flex items-center">
            <RefreshCw size={14} className="mr-2" />
            Refreshing in {refreshCountdown}s
          </div>
        </div>
      </div>

      {/* Add environment indicator */}
      <div className="bg-muted/50 p-3 rounded-lg border text-sm">
        <div className="flex items-center gap-2">
          <div
            className={`w-2 h-2 rounded-full ${typeof process !== "undefined" && process.versions?.node ? "bg-green-500" : "bg-yellow-500"}`}
          ></div>
          <span className="text-muted-foreground">
            {typeof process !== "undefined" && process.versions?.node
              ? "Connected to real Docker environment"
              : "Preview mode - using simulated data"}
          </span>
        </div>
      </div>

      <div className="bg-card p-6 rounded-lg border shadow-sm">
        <div className="flex flex-col md:flex-row items-center gap-4">
          <div className="text-4xl">🚜 🌾</div>
          <div>
            <h1 className="text-3xl font-bold text-primary">Farmboy Status Dashboard</h1>
            <p className="text-muted-foreground">
              Last Updated: <strong>{new Date(data.timestamp).toLocaleString()}</strong>
            </p>
            <p className="text-muted-foreground">
              Containers:{" "}
              <strong>
                {data.performance.active_containers}/{data.performance.total_containers} Running
              </strong>
            </p>
          </div>
        </div>
      </div>

      <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
        <Card>
          <CardContent className="pt-6">
            <h3 className="text-xl font-semibold mb-4 flex items-center gap-2 text-primary">
              <Server size={20} /> System Status
            </h3>

            <div className="space-y-3">
              <div className="flex justify-between items-center pb-2 border-b">
                <span className="text-muted-foreground font-medium">Server Status</span>
                <span className={`px-2 py-1 rounded text-xs font-medium ${getStatusClass(data.system_status.server)}`}>
                  {data.system_status.server.charAt(0).toUpperCase() + data.system_status.server.slice(1)}
                </span>
              </div>

              <div className="flex justify-between items-center pb-2 border-b">
                <span className="text-muted-foreground font-medium">Bot Farm Status</span>
                <span
                  className={`px-2 py-1 rounded text-xs font-medium ${getStatusClass(data.system_status.bot_farm)}`}
                >
                  {data.system_status.bot_farm.charAt(0).toUpperCase() + data.system_status.bot_farm.slice(1)}
                </span>
              </div>

              <div className="flex justify-between items-center pb-2 border-b">
                <span className="text-muted-foreground font-medium">Database Connection</span>
                <span
                  className={`px-2 py-1 rounded text-xs font-medium ${getStatusClass(data.system_status.database)}`}
                >
                  {data.system_status.database.charAt(0).toUpperCase() + data.system_status.database.slice(1)}
                </span>
              </div>

              <div className="flex justify-between items-center">
                <span className="text-muted-foreground font-medium">API Status</span>
                <span className={`px-2 py-1 rounded text-xs font-medium ${getStatusClass(data.system_status.api)}`}>
                  {data.system_status.api.charAt(0).toUpperCase() + data.system_status.api.slice(1)}
                </span>
              </div>
            </div>
          </CardContent>
        </Card>

        <Card>
          <CardContent className="pt-6">
            <h3 className="text-xl font-semibold mb-4 flex items-center gap-2 text-primary">
              <Cpu size={20} /> Performance Metrics
            </h3>

            <div className="space-y-3">
              <div className="flex justify-between items-center pb-2 border-b">
                <span className="text-muted-foreground font-medium">Active Bots</span>
                <span className="px-2 py-1 rounded text-xs font-medium bg-blue-500 text-white">
                  {data.performance.active_bots}/{data.performance.total_bots}
                </span>
              </div>

              <div className="flex justify-between items-center pb-2 border-b">
                <span className="text-muted-foreground font-medium">Active Containers</span>
                <span className="px-2 py-1 rounded text-xs font-medium bg-blue-500 text-white">
                  {data.performance.active_containers}/{data.performance.total_containers}
                </span>
              </div>

              <div className="flex justify-between items-center pb-2 border-b">
                <span className="text-muted-foreground font-medium">CPU Usage</span>
                <span
                  className={`px-2 py-1 rounded text-xs font-medium ${data.performance.cpu_usage > 80 ? "bg-yellow-500 text-white" : "bg-green-500 text-white"}`}
                >
                  {data.performance.cpu_usage}%
                </span>
              </div>

              <div className="flex justify-between items-center pb-2 border-b">
                <span className="text-muted-foreground font-medium">Memory Usage</span>
                <span
                  className={`px-2 py-1 rounded text-xs font-medium ${data.performance.memory_usage > 85 ? "bg-yellow-500 text-white" : "bg-green-500 text-white"}`}
                >
                  {data.performance.memory_usage}%
                </span>
              </div>

              <div className="flex justify-between items-center">
                <span className="text-muted-foreground font-medium">Uptime</span>
                <span className="px-2 py-1 rounded text-xs font-medium bg-blue-500 text-white">
                  {data.performance.uptime}
                </span>
              </div>
            </div>
          </CardContent>
        </Card>

        <Card>
          <CardContent className="pt-6">
            <h3 className="text-xl font-semibold mb-4 flex items-center gap-2 text-primary">
              <BarChart size={20} /> Farm Statistics
            </h3>

            <div className="space-y-3">
              <div className="flex justify-between items-center pb-2 border-b">
                <span className="text-muted-foreground font-medium">Total GP Earned</span>
                <span className="px-2 py-1 rounded text-xs font-medium bg-green-500 text-white">
                  {formatNumber(data.statistics.total_gp)}
                </span>
              </div>

              <div className="flex justify-between items-center pb-2 border-b">
                <span className="text-muted-foreground font-medium">Items Collected</span>
                <span className="px-2 py-1 rounded text-xs font-medium bg-blue-500 text-white">
                  {formatNumber(data.statistics.items_collected)}
                </span>
              </div>

              <div className="flex justify-between items-center pb-2 border-b">
                <span className="text-muted-foreground font-medium">Successful Runs</span>
                <span className="px-2 py-1 rounded text-xs font-medium bg-green-500 text-white">
                  {formatNumber(data.statistics.successful_runs)}
                </span>
              </div>

              <div className="flex justify-between items-center">
                <span className="text-muted-foreground font-medium">Error Rate</span>
                <span
                  className={`px-2 py-1 rounded text-xs font-medium ${data.statistics.error_rate > 5 ? "bg-yellow-500 text-white" : "bg-green-500 text-white"}`}
                >
                  {data.statistics.error_rate}%
                </span>
              </div>
            </div>
          </CardContent>
        </Card>
      </div>

      {Object.keys(data.containers).length > 0 && (
        <Card>
          <CardContent className="pt-6">
            <h3 className="text-xl font-semibold mb-4 flex items-center gap-2 text-primary">
              <Container size={20} /> Container Status
            </h3>

            <div className="overflow-x-auto">
              <table className="w-full">
                <thead>
                  <tr className="border-b">
                    <th className="text-left py-3 px-4 font-medium">Container</th>
                    <th className="text-left py-3 px-4 font-medium">Image</th>
                    <th className="text-left py-3 px-4 font-medium">Status</th>
                    <th className="text-left py-3 px-4 font-medium">Health</th>
                    <th className="text-left py-3 px-4 font-medium">Uptime</th>
                  </tr>
                </thead>
                <tbody>
                  {Object.entries(data.containers).map(([name, container]) => (
                    <tr key={name} className="border-b hover:bg-muted/50">
                      <td className="py-3 px-4 font-medium">{name}</td>
                      <td className="py-3 px-4">{container.image}</td>
                      <td className="py-3 px-4">
                        <span
                          className={`px-2 py-1 rounded-full text-xs font-medium ${getBadgeClass(container.status)}`}
                        >
                          {container.status.charAt(0).toUpperCase() + container.status.slice(1)}
                        </span>
                      </td>
                      <td className="py-3 px-4">
                        <span
                          className={`px-2 py-1 rounded-full text-xs font-medium ${getBadgeClass(container.health)}`}
                        >
                          {container.health.charAt(0).toUpperCase() + container.health.slice(1)}
                        </span>
                      </td>
                      <td className="py-3 px-4">
                        {container.started_at ? getUptime(new Date(container.started_at)) : "N/A"}
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          </CardContent>
        </Card>
      )}

      {data.bots.length > 0 && (
        <Card>
          <CardContent className="pt-6">
            <h3 className="text-xl font-semibold mb-4 flex items-center gap-2 text-primary">
              <Bot size={20} /> Active Bot Instances
            </h3>

            <div className="overflow-x-auto">
              <table className="w-full">
                <thead>
                  <tr className="border-b">
                    <th className="text-left py-3 px-4 font-medium">Bot ID</th>
                    <th className="text-left py-3 px-4 font-medium">Account</th>
                    <th className="text-left py-3 px-4 font-medium">Activity</th>
                    <th className="text-left py-3 px-4 font-medium">Status</th>
                    <th className="text-left py-3 px-4 font-medium">Runtime</th>
                    <th className="text-left py-3 px-4 font-medium">GP/Hour</th>
                  </tr>
                </thead>
                <tbody>
                  {data.bots.map((bot) => (
                    <tr key={bot.id} className="border-b hover:bg-muted/50">
                      <td className="py-3 px-4 font-medium">{bot.id}</td>
                      <td className="py-3 px-4">{bot.account}</td>
                      <td className="py-3 px-4">{bot.activity}</td>
                      <td className="py-3 px-4">
                        <span className={`px-2 py-1 rounded-full text-xs font-medium ${getBadgeClass(bot.status)}`}>
                          {bot.status.charAt(0).toUpperCase() + bot.status.slice(1)}
                        </span>
                      </td>
                      <td className="py-3 px-4">{bot.runtime}</td>
                      <td className="py-3 px-4">{formatNumber(bot.gp_hour)}</td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          </CardContent>
        </Card>
      )}

      {data.activity_log.length > 0 && (
        <Card>
          <CardContent className="pt-6">
            <h3 className="text-xl font-semibold mb-4 flex items-center gap-2 text-primary">
              <Activity size={20} /> Recent Activity Log
            </h3>

            <div className="overflow-x-auto">
              <table className="w-full">
                <thead>
                  <tr className="border-b">
                    <th className="text-left py-3 px-4 font-medium">Timestamp</th>
                    <th className="text-left py-3 px-4 font-medium">Source</th>
                    <th className="text-left py-3 px-4 font-medium">Event</th>
                    <th className="text-left py-3 px-4 font-medium">Details</th>
                    <th className="text-left py-3 px-4 font-medium">Status</th>
                  </tr>
                </thead>
                <tbody>
                  {data.activity_log.map((log, index) => (
                    <tr key={index} className="border-b hover:bg-muted/50">
                      <td className="py-3 px-4">{log.timestamp}</td>
                      <td className="py-3 px-4">{log.bot}</td>
                      <td className="py-3 px-4">{log.event}</td>
                      <td className="py-3 px-4">{log.details}</td>
                      <td className="py-3 px-4">
                        <span className={`px-2 py-1 rounded-full text-xs font-medium ${getBadgeClass(log.status)}`}>
                          {log.status.charAt(0).toUpperCase() + log.status.slice(1)}
                        </span>
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          </CardContent>
        </Card>
      )}
    </div>
  )
}

// Helper function to calculate uptime from a date
function getUptime(startDate: Date): string {
  const diff = Date.now() - startDate.getTime()
  const hours = Math.floor(diff / (1000 * 60 * 60))
  const minutes = Math.floor((diff % (1000 * 60 * 60)) / (1000 * 60))
  return `${hours}h ${minutes}m`
}
