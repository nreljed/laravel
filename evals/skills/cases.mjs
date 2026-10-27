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

    // Design & UI skills — routing cases added to lock in trigger vocabulary.
    {
        id: "distinctive-visual-design",
        expect: "frontend-design",
        prompt: "Design a distinctive landing page with a unique visual identity",
        requiredTerms: ["visual design", "typography", "aesthetic"],
    },
    {
        id: "anti-slop-frontend",
        expect: "design-taste-frontend",
        prompt: "Redesign this landing page so it doesn't look like a template",
        requiredTerms: ["frontend", "landing page", "redesign"],
    },
    {
        id: "design-redesign-audit",
        expect: "impeccable",
        prompt: "Audit and polish this UI for accessibility and visual hierarchy",
        requiredTerms: ["design", "redesign", "audit", "polish"],
    },
    {
        id: "ui-polish-philosophy",
        expect: "emil-design-eng",
        prompt: "Polish this component with better animation and micro-interactions",
        requiredTerms: ["ui polish", "component", "animation"],
    },
    {
        id: "upgrade-existing-site",
        expect: "redesign-existing-projects",
        prompt: "Upgrade this existing website to premium quality",
        requiredTerms: ["redesign", "upgrade", "premium"],
    },
    {
        id: "site-theming-tokens",
        expect: "frontend-site-theming",
        prompt: "Set up site design tokens and theme for the public marketing pages",
        requiredTerms: ["site", "theme", "design token"],
    },
    {
        id: "tailwind-responsive-layout",
        expect: "tailwindcss-development",
        prompt: "Build a responsive grid layout with Tailwind dark mode",
        requiredTerms: ["tailwind", "responsive", "dark mode"],
    },
    {
        id: "web-interface-guidelines",
        expect: "web-design-guidelines",
        prompt: "Review my UI for accessibility and web interface guidelines",
        requiredTerms: ["web interface", "guidelines", "accessibility"],
    },
    {
        id: "professional-page-craft",
        expect: "design-pagecraft",
        prompt: "Build a professional landing page with hero and scroll animation",
        requiredTerms: ["page", "landing", "hero", "scroll animation"],
    },
    {
        id: "build-animation",
        expect: "animate",
        prompt: "Animate this component with a smooth transition on mount",
        requiredTerms: ["animation", "motion", "transition"],
    },
    {
        id: "find-animation-opportunities",
        expect: "find-animation-opportunities",
        prompt: "Find places in this UI that could be animated",
        requiredTerms: ["animation", "animate", "motion"],
    },
    {
        id: "review-animation-code",
        expect: "review-animations",
        prompt: "Review this animation code for performance and polish",
        requiredTerms: ["animation", "motion", "review"],
    },
    {
        id: "gsap-core-api",
        expect: "gsap-core",
        prompt: "Use GSAP to animate an element with easing and stagger",
        requiredTerms: ["gsap", "animation", "tween"],
    },
    {
        id: "gsap-scroll-trigger",
        expect: "gsap-scrolltrigger",
        prompt: "Create a scroll-triggered parallax animation with GSAP",
        requiredTerms: ["scrolltrigger", "scroll", "gsap"],
    },
    {
        id: "gsap-performance-optimization",
        expect: "gsap-performance",
        prompt: "Optimize GSAP animation performance and reduce jank",
        requiredTerms: ["gsap", "performance", "optim"],
    },
    {
        id: "gsap-plugin-registration",
        expect: "gsap-plugins",
        prompt: "Register and use a GSAP plugin like ScrollTo or Flip",
        requiredTerms: ["gsap", "plugin"],
    },
    {
        id: "gsap-react-hook",
        expect: "gsap-react",
        prompt: "Use useGSAP hook in React for animation cleanup",
        requiredTerms: ["gsap", "react", "usegsap"],
    },
    {
        id: "gsap-timeline-sequencing",
        expect: "gsap-timeline",
        prompt: "Sequence animations with a GSAP timeline",
        requiredTerms: ["gsap", "timeline", "sequenc"],
    },
    {
        id: "animation-term-glossary",
        expect: "animation-vocabulary",
        prompt: "What is the term for the bouncy effect when a popover opens",
        requiredTerms: ["animation", "motion", "effect"],
    },
    {
        id: "queued-job-dispatch",
        expect: "jobs-and-queues",
        prompt: "Dispatch a queued job with retry and backoff",
        requiredTerms: ["queue", "job", "dispatch"],
    },
    {
        id: "send-email-template",
        expect: "mail-and-notifications",
        prompt: "Send an email using a Mailable template",
        requiredTerms: ["mail", "email", "mailable"],
    },
    {
        id: "telescope-debug-queries",
        expect: "telescope-debugging",
        prompt: "Debug slow queries using Telescope",
        requiredTerms: ["telescope", "debug", "quer"],
    },
    {
        id: "react-hook-form-validation",
        expect: "react-hook-form",
        prompt: "Build a form with useForm and zod validation",
        requiredTerms: ["useform", "form", "zod"],
    },
    {
        id: "error-exception-handling",
        expect: "error-handling",
        prompt: "Handle an exception and show an error page",
        requiredTerms: ["error", "exception", "handling"],
    },
    {
        id: "data-visualization-charts",
        expect: "data-visualization",
        prompt: "Build a chart with Recharts and KPI cards for analytics",
        requiredTerms: ["chart", "recharts", "kpi"],
    },
    {
        id: "dashboard-layout-grid",
        expect: "dashboard-layout",
        prompt: "Create a responsive dashboard grid with metric cards",
        requiredTerms: ["dashboard", "grid", "metric"],
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