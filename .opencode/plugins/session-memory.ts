import { randomUUID } from 'node:crypto'
import { existsSync, mkdirSync, readFileSync, writeFileSync } from 'node:fs'
import { join, dirname } from 'node:path'

const DEFAULTS = {
  maxItems: 100,
  store: '.opencode/session-memory.json',
}

export default (async ({ project, directory }) => {
  const root = project?.root || directory
  const cfg = { ...DEFAULTS }
  const storePath = join(root, cfg.store)

  const load = () => {
    try {
      if (!existsSync(storePath)) return []
      const raw = readFileSync(storePath, 'utf8')
      const arr = JSON.parse(raw)
      return Array.isArray(arr) ? arr : []
    } catch {
      return []
    }
  }

  const save = (items) => {
    try {
      mkdirSync(dirname(storePath), { recursive: true })
      const trimmed = items.slice(-cfg.maxItems)
      writeFileSync(storePath, JSON.stringify(trimmed, null, 2))
    } catch {
      // best-effort; never block the session
    }
  }

  return {
    // Persist compacted summary as durable session memory
    'experimental.session.compacting': (input) => {
      try {
        const items = load()
        const summary = input?.summary || input?.text || ''
        if (summary && summary.trim().length > 0) {
          items.push({
            id: randomUUID(),
            ts: Date.now(),
            summary: summary.trim(),
          })
          save(items)
        }
      } catch {
        // ignore
      }
    },
  }
})
