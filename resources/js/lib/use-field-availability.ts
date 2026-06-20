import { useEffect, useRef, useState } from "react"

export function useDebounce<T>(value: T, delay: number): T {
  const [debouncedValue, setDebouncedValue] = useState<T>(value)

  useEffect(() => {
    const timer = setTimeout(() => setDebouncedValue(value), delay)
    return () => clearTimeout(timer)
  }, [value, delay])

  return debouncedValue
}

export function useFieldAvailability(field: "username" | "email") {
  const [value, setValue] = useState("")
  const [status, setStatus] = useState<"idle" | "checking" | "taken" | "available">("idle")
  const debouncedValue = useDebounce(value, 400)
  const lastChecked = useRef("")

  useEffect(() => {
    if (!debouncedValue || debouncedValue.length < 3) {
      setStatus("idle")
      return
    }

    if (debouncedValue === lastChecked.current) return

    const controller = new AbortController()

    setStatus("checking")

    fetch(
      route("register.availability", { field, value: debouncedValue }),
      { signal: controller.signal },
    )
      .then((res) => res.json())
      .then((data) => {
        lastChecked.current = debouncedValue
        setStatus(data.available ? "available" : "taken")
      })
      .catch(() => {})

    return () => controller.abort()
  }, [debouncedValue, field])

  return { status, setValue }
}
