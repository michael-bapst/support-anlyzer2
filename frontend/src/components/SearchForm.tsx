"use client"

import { useState, ChangeEvent } from "react"
import { Input } from "@/components/ui/input"
import { Button } from "@/components/ui/button"
import { MatchCard } from "./MatchCard"
import { fetchMatches } from "@/lib/api"
import { MatchResult } from "@/types/match"

export function SearchForm() {
    const [input, setInput] = useState("")
    const [results, setResults] = useState<MatchResult[]>([])
    const [loading, setLoading] = useState(false)

    async function handleSearch() {
        setLoading(true)
        try {
            const res = await fetchMatches(input)
            setResults(res)
        } catch (e) {
            console.error(e)
        } finally {
            setLoading(false)
        }
    }

    return (
        <div className="flex flex-col gap-6 max-w-2xl mx-auto w-full">
            <div className="flex gap-2">
                <Input
                    className="w-full"
                    placeholder="Fehlercode oder Beschreibung…"
                    value={input}
                    type="text"
                    onChange={(e: ChangeEvent<HTMLInputElement>) => setInput(e.target.value)}
                />
                <Button
                    className="shrink-0"
                    type="button"
                    onClick={handleSearch}
                    disabled={loading}
                    variant="default"
                    size="default"
                >
                    {loading ? "Suchen…" : "Suchen"}
                </Button>
            </div>

            <div className="flex flex-col gap-4">
                {results.map((r) => (
                    <MatchCard key={r.id} match={r} />
                ))}
            </div>
        </div>
    )
}
