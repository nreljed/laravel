/**
 * Skill routing cases.
 *
 * WHAT THIS EVALS, PRECISELY:
 *
 * It does NOT test whether a language model would route a prompt to a skill.
 * That needs a model in the loop, which makes the eval non-deterministic and
 * expensive to run on every push. Instead it tests the thing that actually rots
 * silently and is checkable offline: whether each skill's description still
 * contains the vocabulary its routing depends on.
 *
 * A skill description is the only thing the router sees before loading the body.
 * If someone trims "Artisan commands and scheduling" down to "CLI helpers", the
 * skill still exists, still parses, still passes a frontmatter check -- and stops
 * matching any prompt about artisan or the scheduler. Nothing else in this repo
 * would notice. These cases notice.
 *
 * `requiredTerms` must all appear in the skill's name + description. `absentTerms`
 * must NOT all appear, which guards against the opposite failure: a description so
 * broad it swallows unrelated prompts.
 *
 * Terms are matched as case-insensitive substrings after stripping punctuation, so
 * "scheduling" matches "schedule" only if written that way -- keep terms exactly
 * as they should appear.
 */

export const CASES = [
    {
        id: "artisan-command-scheduling",
        expect: "console-commands",
        prompt: "Add an artisan command and register it on the scheduler",
        requiredTerms: ["artisan", "command", "schedul"],
    },
    {
        id: "query-index-tuning",
        expect: "database-performance",
        prompt: "This page is slow, profile the queries and add the missing index",
        requiredTerms: ["quer", "index", "cach"],
    },
    {
        id: "n-plus-one",
        expect: "database-performance",
        prompt: "Fix the N+1 query problem in the dashboard index",
        // Routing for an N+1 prompt depends on the literal "N+1" appearing; the
        // description does not promise "eager loading", so requiring it would
        // encode a claim the skill never made.
        requiredTerms: ["n+1"],
    },
    {
        id: "workflow-yaml",
        expect: "deployment-pipeline",
        prompt: "The GitHub Actions deploy workflow fails on the runner",
        requiredTerms: ["github action", "workflow", "deploy"],
    },
    {
        id: "inertia-form",
        expect: "inertia-react-development",
        prompt: "Build a React form page with useForm and server-side validation",
        requiredTerms: ["inertia", "react", "useform"],
    },
    {
        id: "notification-bell",
        expect: "notification-system",
        prompt: "Wire up the notification bell and the polling endpoint",
        requiredTerms: ["notification"],
    },
    {
        id: "media-upload-webp",
        expect: "media-uploads",
        prompt: "Add a featured image picker with WebP conversion",
        requiredTerms: ["upload", "image"],
    },
    {
        id: "security-review",
        expect: "security-hardening",
        prompt: "Harden the auth flow: CSRF exemptions and rate limiting",
        requiredTerms: ["secur", "auth", "csrf"],
    },
    {
        id: "test-factory",
        expect: "testing-patterns",
        prompt: "Write a PHPUnit feature test using the shared CreatesTestUsers trait",
        requiredTerms: ["phpunit", "test"],
    },
    {
        id: "tailwind-tokens",
        expect: "tailwindcss-development",
        prompt: "Style the dashboard cards with Tailwind and dark mode variants",
        requiredTerms: ["tailwind", "dark mode"],
    },
    {
        id: "laravel-controller",
        expect: "laravel-best-practices",
        prompt: "Add a controller, form request and policy for the new resource",
        requiredTerms: ["laravel", "controller", "model"],
    },
    {
        id: "ai-agent-provider",
        expect: "ai-sdk-development",
        prompt: "Build a streaming Laravel AI agent with per-user BYOK keys",
        requiredTerms: ["laravel ai", "agent", "stream"],
    },

    // Disambiguation: these guard against descriptions that are too broad and
    // would steal routing from each other. Each case asserts a skill must NOT
    // look like a match for an unrelated prompt.
    {
        id: "not-deploy-for-scheduling",
        expect: "console-commands",
        prompt: "Schedule an artisan command",
        absentTerms: ["github action", "workflow yml", "self-hosted runner"],
        note: "console-commands must not advertise deployment vocabulary",
    },
    {
        id: "not-notifications-for-security",
        expect: "security-hardening",
        prompt: "Harden CSRF on the login form",
        absentTerms: ["notification bell", "polling", "unread count"],
        note: "security-hardening must not advertise notification vocabulary",
    },
]