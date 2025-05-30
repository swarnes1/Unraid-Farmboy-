import { Suspense } from "react"
import { FarmboyDashboard } from "@/components/farmboy-dashboard"
import { fetchContainerData } from "@/app/actions/container-actions"

export default async function DashboardPage() {
  const farmboyData = await fetchContainerData()

  return (
    <div className="container mx-auto px-4 py-8">
      <Suspense
        fallback={
          <div className="flex items-center justify-center min-h-screen">
            <div className="text-center">
              <div className="animate-spin rounded-full h-32 w-32 border-b-2 border-primary mx-auto"></div>
              <p className="mt-4 text-lg">Loading Farmboy Dashboard...</p>
            </div>
          </div>
        }
      >
        <FarmboyDashboard data={farmboyData} />
      </Suspense>
    </div>
  )
}
