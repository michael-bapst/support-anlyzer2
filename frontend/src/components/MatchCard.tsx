"use client"

import { Card } from "@/components/ui/card"
import { Badge } from "@/components/ui/badge"
import { MatchResult } from "@/types/match"

export function MatchCard({ match }: { match: MatchResult }) {
    return (
        <Card className="p-4 flex flex-col gap-2 shadow-md dark:bg-zinc-900">
            <div className="text-sm text-muted-foreground">
                {match.category || "Unbekannt"} • {match.code || "Kein Code"}
            </div>
            <div className="font-medium text-base">{match.solution_text}</div>
            <div className="flex gap-2 flex-wrap pt-1">
                {match.tags?.map((tag) => (
                    <Badge key={tag} variant="outline" className="text-xs">{tag}</Badge>
                ))}
            </div>
        </Card>
    )
}
