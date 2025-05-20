import { MatchResult } from "@/types/match"

export async function fetchMatches(input: string): Promise<MatchResult[]> {
    const res = await fetch(`/api/match?text=${encodeURIComponent(input)}`)
    if (!res.ok) throw new Error("Fehler bei der Anfrage")
    return res.json()
}
