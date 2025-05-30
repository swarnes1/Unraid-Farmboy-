import { Card, CardHeader, CardTitle, CardDescription, CardContent } from "../components/ui/card"
import Link from "next/link"

export default function Page() {
  return (
    <Card>
      <CardHeader>
        <CardTitle>Example Card</CardTitle>
        <CardDescription>A simple card component.</CardDescription>
      </CardHeader>
      <CardContent>
        This is the content of the card.
        <Link
          href="/dashboard"
          className="px-4 py-2 bg-primary text-white rounded-md hover:bg-primary/90 transition-colors"
        >
          View Farmboy Dashboard
        </Link>
      </CardContent>
    </Card>
  )
}
