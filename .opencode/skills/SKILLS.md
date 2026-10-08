# Project Skills Index — `.opencode/skills` (25 skills)

Hardware skills for the nreljed stack (platform + design). Mirrored from the
global installs in `~/.config/opencode/skills` (platform) and
`~/.agents/skills` (design/animation) — keep the mirror copies byte-identical.

Layer legend: **Backend** · **Frontend** · **Full-stack** · **Infra** · **Meta** (harness/tooling).

## Backend / Platform
| Skill | Layer | Purpose |
|---|---|---|
| `feature-architecture` | Full-stack | Self-contained FeatureProvider modules; registration contract. |
| `permission-system` | Backend | Slug-based RBAC cataloging, seeding, gates, middleware. |
| `deploy` | Full-stack | CI/CD pipeline, /deploy endpoints, releases, tokens. |
| `self-hosted-runners` | Infra | Windows self-hosted GitHub Actions runners. |

## Frontend / Design
| Skill | Layer | Purpose |
|---|---|---|
| `design-pagecraft` | Frontend | Page-craft workflow: route → PRODUCT/DESIGN → implement → verify. |
| `frontend-site-theming` | Frontend | Public-site design-token theming (light + dark variants). |
| `design-taste-frontend` | Frontend | Anti-slop design taste for landing pages and redesigns. |
| `frontend-design` | Frontend | Distinctive intentional visual design. |
| `emil-design-eng` | Frontend | Emil Kowalski UI polish and motion philosophy. |
| `impeccable` | Frontend | Shaping/auditing/critiquing interfaces (detector CLI). |
| `web-design-guidelines` | Frontend | Web Interface Guidelines compliance review. |
| `lexical-editor-development` | Frontend | Lexical rich-text editor node/plugin work. |

## Frontend / Motion (GSAP + animation)
| Skill | Layer | Purpose |
|---|---|---|
| `animate` | Frontend | Build animations that feel right, decisions in order. |
| `animation-vocabulary` | Frontend | Reverse-lookup: vague motion words → concrete terms. |
| `find-animation-opportunities` | Frontend | Hunt places that should animate but don't. |
| `review-animations` | Frontend | Critique motion against Emil's craft bar. |
| `redesign-existing-projects` | Frontend | Upgrade existing sites/apps to premium quality. |
| `gsap-core` | Frontend | gsap.to/from/fromTo, easing, stagger. |
| `gsap-react` | Frontend | useGSAP, refs, cleanup in React. |
| `gsap-timeline` | Frontend | Timeline orchestration, position params. |
| `gsap-scrolltrigger` | Frontend | Scroll-linked animation, pinning, scrub. |
| `gsap-plugins` | Frontend | ScrollToPlugin, ScrollSmoother, Flip, etc. |
| `gsap-performance` | Frontend | Transforms, no layout thrash, GPU hints. |

## Meta / Harness
| Skill | Layer | Purpose |
|---|---|---|
| `skill-creator` | Meta | Create/iterate skills; eval benchmarks. |
| `webapp-testing` | Meta | Playwright interaction + browser-log tooling. |

Mirror notes: platform skills (`deploy`, `feature-architecture`,
`permission-system`, `self-hosted-runners`, `skill-creator`, `webapp-testing`,
`impeccable`, `lexical-editor-development`) mirror `~/.config/opencode/skills`;
design/motion skills mirror `~/.agents/skills`. Both global roots were
LF-normalized in the same pass — keep them identical here.