import { useMemo, useRef, useState } from "react";
import { Link, usePage } from "@inertiajs/react";
import { ArrowRight, ArrowUpRight } from "lucide-react";
import { useGSAP } from "@gsap/react";
import gsap from "gsap";
import { ScrollTrigger } from "gsap/ScrollTrigger";
import { SeoHead } from "@nreljed/platform/Components/seo/SeoHead";
import { PillButton, SiteMenuOverlay } from "../../Components/site";

const CDN = "https://cdn.prod.website-files.com/6922d75ee39b1fb737dc0680";
const CDN_WORK = "https://cdn.prod.website-files.com/6922d75ee39b1fb737dc068a";
const CDN_UPLOAD = "https://uploads-ssl.webflow.com/6922d75ee39b1fb737dc0680";

const heroImages = {
  one: `${CDN}/697a7509ba377fa8b1e2ef79_f.mp4.webp`,
  two: `${CDN}/697a750965ca90e0ff43be2d_h.mp4.webp`,
  three: `${CDN}/697a75093d77bb3fbf23a558_a.webp`,
};

const logoSvg = `${CDN}/697ba563050d0ce6bd2bca61_Background.svg`;
const contactBadgeSvg = `${CDN}/693762854882533db079b5e6__%20Contact%20Us%20_%20Contact%20Us%20Us%20(1).svg`;

const works = [
  {
    title: "Box Craft",
    year: "2025",
    image: `${CDN_WORK}/6922d75ee39b1fb737dc0b4a_imgi_4_Box-Packaging-PSD-Mockup-Free-1000x750.jpg.webp`,
    full: true,
  },
  {
    title: "SipCup",
    year: "2024",
    image: `${CDN_WORK}/6922d75ee39b1fb737dc0b35_imgi_53_Free-Paper-Coffee-Cup-Mockup-04.jpg.webp`,
    full: false,
  },
  {
    title: "FlowApp UI",
    year: "2024",
    image: `${CDN_WORK}/6922d75ee39b1fb737dc0b4c_imgi_33_iPhone-Mockup-PSD-Scene-01-1600x1043.jpg.webp`,
    full: false,
  },
  {
    title: "Resilient Identity",
    year: "2025",
    image: `${CDN_WORK}/6922d75ee39b1fb737dc0b4b_imgi_37_Product-Boxes-Branding-Free-Mockups-01.jpg.webp`,
    full: true,
  },
];

const services = [
  {
    number: "01",
    name: "Development",
    subtitle: "Technical Excellence & Scalable Engineering",
    description:
      "We build fast, scalable digital systems—so your product performs reliably and grows without limits.",
    list: [
      "Front-End & Back-End Development",
      "Webflow / CMS Development",
      "Performance & Speed Optimization",
      "Scalable Architecture",
      "Security & Maintenance",
    ],
    href: "#",
  },
  {
    number: "02",
    name: "Brand Strategy",
    subtitle: "Strategic Positioning & Brand Architecture",
    description:
      "We define your brand's direction, positioning, and voice—so every design and decision works together.",
    list: [
      "Brand Positioning",
      "Brand Voice & Messaging",
      "Visual Identity Direction",
      "Competitive Analysis",
      "Brand Guidelines",
    ],
    href: "#",
  },
  {
    number: "03",
    name: "Web Design",
    subtitle: "User-Centric Design & Digital Storytelling",
    description:
      "We design intuitive, high-impact websites—so users connect, engage, and take action effortlessly.",
    list: [
      "UX Research & User Flows",
      "UI Design & Design Systems",
      "Responsive Web Design",
      "Conversion-Focused Layouts",
      "Prototyping & Interaction Design",
    ],
    href: "#",
  },
  {
    number: "04",
    name: "Digital Growth",
    subtitle: "Data-Driven Expansion & Market Optimization",
    description:
      "We drive data-led growth strategies—so your brand reaches the right audience and scales sustainably.",
    list: [
      "SEO & Content Optimization",
      "Performance Marketing",
      "Analytics & Insights",
      "Conversion Rate Optimization (CRO)",
      "Growth Strategy & Scaling",
    ],
    href: "#",
  },
];

const testimonials = (brand: string) => [
  {
    quote:
      `${brand} brought structure and clarity to our brand. Their strategic mindset and refined design approach ` +
      "helped us communicate more clearly and connect better with our audience.",
    name: "Emily Foster",
    role: "Sarter Founder",
    avatar: `${CDN}/697a6bcfb28756f2b8dd8776_Ellipse%2026.webp`,
  },
  {
    quote:
      "Professional, thoughtful, and deeply strategic in their approach. We highly value their clarity, discipline, and creative intelligence.",
    name: "Alex Jonas",
    role: "Zirel Founder",
    avatar: `${CDN}/697a6bcffa37944564ceeee6_Ellipse%2025.webp`,
  },
  {
    quote:
      `${brand} has an exceptional eye for detail and a deep understanding of user experience. Their strategic ` +
      "approach helped us improve both engagement and conversion.",
    name: "Alisia Fox",
    role: "PulseZ Co-Founder",
    avatar: `${CDN}/697a6bcf3d612205008cbd3e_Ellipse%2024.webp`,
  },
  {
    quote:
      `From the very first conversation, ${brand} felt more like a partner than an agency. They took the time to ` +
      "understand our challenges and translated complex ideas into a clean, intuitive digital experience.",
    name: "John Doe",
    role: "Marilax Founder",
    avatar: `${CDN}/697a6bcf674716fcb456b541_Ellipse%2027.webp`,
  },
  {
    quote:
      `Working with ${brand} completely changed the way we see our brand. They didn't just design visuals. They ` +
      "helped us understand our identity, our audience, and our direction.",
    name: "Luna Fox",
    role: "Artivo Founder",
    avatar: `${CDN}/697a6bcf28756f2b8dd8776_Ellipse%2026.webp`,
  },
];

const ringImages = [
  heroImages.one,
  works[0]!.image,
  works[1]!.image,
  heroImages.two,
  works[2]!.image,
  `${CDN_UPLOAD}/697a775f1cc7b8f2b5d270ac_faflk.webp`,
  works[3]!.image,
  heroImages.three,
  `${CDN}/69593f0796547747738911ed_noise%20bg_11zon.webp`,
  `${CDN}/697a775f1ad491f52400e81d_noise%20bg.webp`,
  works[0]!.image,
];

const ringPositions = ringImages.map((_, index) => {
  const angle = (index / ringImages.length) * Math.PI * 2 - Math.PI / 2;
  return {
    left: `${50 + Math.cos(angle) * 46}%`,
    top: `${50 + Math.sin(angle) * 32}%`,
    rotate: Math.round(Math.sin(index * 2.7) * 34),
  };
});

const heroRevealLines = ["We design clarity", "we build growth."];

const HeroChars = ({ text, accent }: { text: string; accent?: boolean }) => (
  <span>
    {text.split("").map((ch, i) => (
      <span
        key={`${ch}-${i}`}
        data-hero-char
        className={accent ? "text-site-secondary" : undefined}
      >
        {ch === " " ? "\u{A0}" : ch}
      </span>
    ))}
  </span>
);

const bigStatement = "We combine premium quality with strategic scalability to elevate your brand.";

const menuLinks = [
  { number: "01", label: "Home", href: "/" },
  { number: "02", label: "About", href: "/about" },
  { number: "03", label: "Works", href: "#" },
  { number: "04", label: "Contact", href: "/contact" },
  { number: "05", label: "Services", href: "/services" },
  { number: "06", label: "Pricing", href: "#" },
  { number: "07", label: "Blog", href: "/blog" },
  { number: "08", label: "Style Guide", href: "/style-guide" },
];

export default function Home() {
  const { props } = usePage();
  const brand = (props.brand as { name?: string; logo?: string }) || {};
  const appName = brand.name || "";
  const year = useMemo(() => new Date().getFullYear(), []);
  const rootRef = useRef<HTMLDivElement>(null);
  const exploreRef = useRef<HTMLDivElement>(null);
  const [menuOpen, setMenuOpen] = useState(false);

  useGSAP(
    () => {
      const mm = gsap.matchMedia();
      gsap.registerPlugin(ScrollTrigger);

      mm.add("(prefers-reduced-motion: no-preference)", () => {
        const heroChars = gsap.utils.toArray<HTMLElement>("[data-hero-char]");
        if (heroChars.length > 0) {
          gsap.fromTo(
            heroChars,
            { opacity: 0.15 },
            {
              opacity: 1,
              stagger: 0.05,
              duration: 0.64,
              ease: "none",
              scrollTrigger: {
                trigger: "[data-hero-title]",
                start: "top 55%",
                end: "center 5%",
                scrub: 0.85,
              },
            },
          );
        }

        gsap.from("[data-hero-sub]", {
          y: 20,
          autoAlpha: 0,
          duration: 0.7,
          ease: "power3.out",
          delay: 0.2,
        });

        gsap.from("[data-hero-card]", {
          y: 70,
          autoAlpha: 0,
          scale: 0.96,
          duration: 0.9,
          stagger: 0.09,
          ease: "power2.out",
          delay: 0.5,
        });

        if (exploreRef.current) {
          gsap.from("[data-ring-fig]", {
            scale: 0.05,
            autoAlpha: 0,
            duration: 1.1,
            stagger: 0.06,
            ease: "elastic.out(1, 0.5)",
            scrollTrigger: { trigger: exploreRef.current, start: "top 62%", once: true },
          });
        }

        gsap.from("[data-reveal]", {
          y: 56,
          autoAlpha: 0,
          duration: 0.85,
          ease: "power2.out",
          stagger: 0.08,
          scrollTrigger: { trigger: "[data-reveal-band]", start: "top 75%", once: true },
        });

        const serviceRows = gsap.utils.toArray<HTMLElement>("[data-service-row]");
        for (const row of serviceRows) {
          gsap.from(row, {
            y: 48,
            autoAlpha: 0,
            duration: 0.8,
            ease: "power2.out",
            scrollTrigger: { trigger: row, start: "top 85%", once: true },
          });
        }

        const testimonialCards = gsap.utils.toArray<HTMLElement>("[data-testimonial-item]");
        for (const card of testimonialCards) {
          gsap.from(card, {
            x: 48,
            autoAlpha: 0,
            duration: 0.7,
            ease: "power2.out",
            scrollTrigger: { trigger: card, start: "left 92%", once: true },
          });
        }

        const words = gsap.utils.toArray<HTMLElement>("[data-statement-word]");
        gsap.from(words, {
          yPercent: 105,
          autoAlpha: 0,
          duration: 0.8,
          stagger: 0.03,
          ease: "power3.out",
          scrollTrigger: { trigger: "[data-statement]", start: "top 78%", once: true },
        });

        const parallaxWraps = gsap.utils.toArray<HTMLElement>("[data-parallax]");
        for (const wrap of parallaxWraps) {
          const img = wrap.querySelector("img");
          if (!img) continue;
          gsap.fromTo(
            img,
            { yPercent: -6 },
            {
              yPercent: 6,
              ease: "none",
              scrollTrigger: { trigger: wrap, start: "top bottom", end: "bottom top", scrub: true },
            },
          );
        }

        gsap.fromTo(
          "[data-footer-line-outline]",
          { autoAlpha: 0, y: 40 },
          {
            autoAlpha: 1,
            y: 0,
            duration: 1,
            ease: "power3.out",
            scrollTrigger: { trigger: "[data-footer-cta]", start: "top 70%", once: true },
          },
        );
      });

      return () => mm.revert();
    },
    { scope: rootRef },
  );

  return (
    <div
      ref={rootRef}
      data-site-theme="canvasly"
      className="min-h-screen bg-site-bg font-site-body text-site-ink"
    >
      <SeoHead
        title={`${appName} — We design clarity, we build growth`}
        description="Creative agency focused on strategy, design, and digital experiences."
      />

      <SiteMenuOverlay
        open={menuOpen}
        onClose={() => setMenuOpen(false)}
        links={menuLinks}
        brand={appName}
        logo={logoSvg}
        note="Let's build something that sticks."
      />

      {/* Fixed rail — logo, vertical nav, brand */}
      <header className="pointer-events-none fixed inset-y-0 left-0 z-40 hidden w-40 flex-col items-center py-6 lg:flex">
        <Link href="/" className="pointer-events-auto block">
          <img
            src={logoSvg}
            alt={`${appName} logo`}
            className="h-16 w-16 rounded-full bg-transparent object-contain"
          />
        </Link>
        <nav className="pointer-events-auto mt-10 flex flex-1 flex-col items-center justify-center gap-1">
          {(
            [
              ["Home", "/", true],
              ["About", "/about", false],
              ["Works", "#", false],
              ["Contact", "/contact", false],
            ] as Array<[label: string, href: string, active: boolean]>
          ).map(([label, href, active]) => (
            <Link
              key={label}
              href={href}
              className={`flex h-10 w-10 rotate-0 items-center justify-center text-[11px] font-medium tracking-[0.18em] uppercase transition-colors ${
                active ? "text-site-ink" : "text-site-secondary/50 hover:text-site-ink"
              }`}
            >
              {label}
            </Link>
          ))}
        </nav>
        <span
          className="pointer-events-auto text-[11px] font-medium tracking-[0.2em] uppercase text-site-secondary"
          style={{ writingMode: "vertical-rl" }}
        >
          {appName}
        </span>
      </header>

      {/* Fixed top-right menu + bottom-right lime circle */}
      <div className="fixed right-6 top-6 z-30 sm:right-8 sm:top-8">
        <button
          type="button"
          onClick={() => setMenuOpen((value) => !value)}
          className="inline-flex h-[53px] min-w-28 items-center justify-center gap-2 rounded-site-card bg-site-ink px-7 text-site-bg-strong transition-transform duration-200 hover:scale-[1.03]"
        >
          {menuOpen ? "Close" : "Menu"}
        </button>
      </div>
      <Link
        href="/contact"
        aria-label="Contact Us"
        className="fixed right-6 bottom-6 z-30 hidden h-[139px] w-[139px] items-center justify-center rounded-full bg-site-accent text-site-accent-ink transition-transform duration-200 hover:scale-105 sm:right-8 sm:bottom-8 lg:flex"
      >
        <span className="relative flex h-full w-full items-center justify-center">
          <img
            src={contactBadgeSvg}
            alt="Contact Us circular text"
            className="absolute inset-0 h-full w-full animate-[spin_14s_linear_infinite] object-contain p-2.5"
          />
          <ArrowUpRight className="h-7 w-7" />
        </span>
      </Link>

      <main className="relative lg:pr-20">
        {/* ---------- HERO ---------- */}
        <section className="relative overflow-hidden px-6 pt-32 pb-20 sm:px-10 lg:pl-52 lg:pr-16">
          <div className="mx-auto max-w-site">
            <div className="flex flex-col items-center text-center lg:pl-40">
              <h1
                data-hero-title
                className="font-site-display max-w-[960px] text-[clamp(2.9rem,8vw,7.5rem)] font-bold leading-[0.92] tracking-[-0.05em]"
              >
                {heroRevealLines[0] ? (
                  <span className="block">
                    <HeroChars text={heroRevealLines[0]} />
                  </span>
                ) : null}
                <span className="block">
                  <HeroChars text="we build " />
                  <HeroChars text="growth." accent />
                </span>
              </h1>
              <p
                data-hero-sub
                className="mt-8 max-w-[789px] text-lg leading-relaxed text-site-ink/70 sm:text-xl"
              >
                {appName} is a creative agency focused on strategy, design, and digital experiences
                that help brands grow with intention.
              </p>
            </div>

            <div className="mt-16 grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-5">
              <div data-hero-card className="mt-0 overflow-hidden rounded-site-card">
                <img
                  src={heroImages.one}
                  alt={`${appName} team collaborating`}
                  className="aspect-[340/423] w-full object-cover"
                />
              </div>
              <div
                data-hero-card
                className="mt-6 flex flex-col justify-between rounded-site-card bg-site-alt-1 p-6 sm:p-8"
              >
                <h2 className="text-3xl font-bold tracking-tight sm:text-4xl">+5 Years</h2>
                <div className="mt-6 space-y-2">
                  <p className="text-sm">Brand experience</p>
                  <div className="h-1.5 w-16 rounded-full bg-site-ink" />
                  <p className="text-sm">Experiences that stick</p>
                </div>
              </div>
              <div
                data-hero-card
                className="col-span-2 mt-3 overflow-hidden rounded-site-card sm:col-span-1 sm:mt-10"
              >
                <img
                  src={heroImages.two}
                  alt={`${appName} designer at work`}
                  className="aspect-[340/423] w-full object-cover"
                />
              </div>
              <div
                data-hero-card
                className="mt-10 flex flex-col justify-between rounded-site-card bg-site-alt-2 p-6 sm:p-8"
              >
                <h2 className="text-3xl font-bold tracking-tight sm:text-4xl">+30 brands</h2>
                <div className="mt-6 space-y-2">
                  <p className="text-sm">Built to scale</p>
                  <div className="h-1.5 w-16 rounded-full bg-site-ink" />
                  <p className="text-sm">Grow without limits</p>
                </div>
              </div>
              <div
                data-hero-card
                className="col-span-2 mt-6 overflow-hidden rounded-site-card sm:col-span-1 sm:mt-14"
              >
                <img
                  src={heroImages.three}
                  alt={`${appName} creative strategist`}
                  className="aspect-[340/423] w-full object-cover"
                />
              </div>
            </div>
          </div>
        </section>

        {/* ---------- APPROACH / STATEMENT ---------- */}
        <section className="px-6 py-24 sm:px-10 lg:pl-52 lg:pr-16">
          <div className="mx-auto max-w-site lg:pl-40">
            <div data-reveal-band className="lg:pl-24">
              <p
                data-reveal
                className="font-site-display max-w-[640px] text-[clamp(2rem,5.5vw,5.2rem)] font-bold leading-[0.98] tracking-[-0.04em]"
              >
                Design is not decoration. It is{" "}
                <span className="text-site-secondary">direction</span>
              </p>
              <p
                data-reveal
                className="mt-8 max-w-[640px] text-lg leading-relaxed text-site-ink/70 sm:text-xl"
              >
                At {appName}, design is more than visual appeal. It&apos;s a strategic tool that
                brings clarity, purpose, and direction to brands.
              </p>
              <div data-reveal className="mt-10">
                <PillButton href="/about">
                  Our Approach
                  <ArrowRight className="h-4 w-4" />
                </PillButton>
              </div>
            </div>

            <div data-statement className="mt-20 lg:mt-28">
              <h2
                data-statement-wrap
                className="font-site-display w-full max-w-[960px] text-[clamp(2.2rem,6vw,5.8rem)] font-bold leading-[0.95] tracking-[-0.04em]"
              >
                {bigStatement.split(" ").map((word, i) => (
                  <span
                    key={`${word}-${i}`}
                    className="inline-block overflow-hidden pb-px align-top"
                  >
                    <span data-statement-word className="inline-block">
                      {word}
                      {i < bigStatement.split(" ").length - 1 ? "\u{A0}" : ""}
                    </span>
                  </span>
                ))}
              </h2>
            </div>
          </div>
        </section>

        {/* ---------- FEATURED WORK ---------- */}
        <section className="px-6 py-24 sm:px-10 lg:pl-52 lg:pr-16">
          <div className="mx-auto max-w-site lg:pl-40">
            <div data-reveal-band className="max-w-[640px]">
              <h2
                data-reveal
                className="font-site-display text-[clamp(2.6rem,9vw,9.5rem)] leading-[0.9] font-bold tracking-[-0.05em]"
              >
                Featured work
              </h2>
            </div>
            <p
              data-reveal
              className="mt-4 max-w-[789px] text-lg leading-relaxed text-site-ink/70 sm:text-xl"
            >
              We create innovative and purposeful designs that not only capture attention but also
              drive meaningful results.
            </p>

            <div className="mt-16 space-y-16">
              {works.map((work) =>
                work.full ? (
                  <div key={work.title} className="group">
                    <div data-parallax className="overflow-hidden rounded-site-card">
                      <img
                        src={work.image}
                        alt={work.title}
                        loading="lazy"
                        className="aspect-[1280/700] w-full scale-[1.12] object-cover transition-transform duration-500 group-hover:scale-[1.16]"
                      />
                    </div>
                    <div className="mt-6 flex items-baseline justify-between">
                      <h3 className="text-2xl font-bold tracking-tight sm:text-4xl">
                        {work.title}
                      </h3>
                      <span className="text-lg text-site-ink-faint">{work.year}</span>
                    </div>
                  </div>
                ) : null,
              )}
              <div className="grid gap-16 sm:grid-cols-2 sm:gap-8">
                {works
                  .filter((work) => !work.full)
                  .map((work) => (
                    <div key={work.title} className="group">
                      <div data-parallax className="overflow-hidden rounded-site-card">
                        <img
                          src={work.image}
                          alt={work.title}
                          loading="lazy"
                          className="aspect-[629/690] w-full scale-[1.12] object-cover transition-transform duration-500 group-hover:scale-[1.16]"
                        />
                      </div>
                      <div className="mt-6 flex items-baseline justify-between">
                        <h3 className="text-2xl font-bold tracking-tight sm:text-3xl">
                          {work.title}
                        </h3>
                        <span className="text-lg text-site-ink-faint">{work.year}</span>
                      </div>
                    </div>
                  ))}
              </div>
            </div>
          </div>
        </section>

        {/* ---------- EXPLORE OUR THINKING ---------- */}
        <section ref={exploreRef} className="relative px-6 py-24 sm:px-10 lg:pl-52 lg:pr-16">
          <div className="relative mx-auto hidden aspect-square w-full max-w-[1060px] sm:block lg:max-w-[1180px]">
            {ringPositions.map((pos, index) => (
              <img
                key={`${pos.left}-${index}`}
                data-ring-fig
                src={ringImages[index]}
                alt=""
                loading="lazy"
                className="absolute h-[15%] w-[15%] rounded-site-card object-cover"
                style={{
                  left: pos.left,
                  top: pos.top,
                  transform: `translate(-50%, -50%) rotate(${pos.rotate}deg)`,
                }}
              />
            ))}
            <h2 className="font-site-display absolute inset-0 flex items-center justify-center px-8 text-center text-[clamp(2.4rem,6vw,6.6rem)] font-bold leading-[0.92] tracking-[-0.05em]">
              Explore our thinking
            </h2>
          </div>
          <h2 className="font-site-display px-0 text-center text-[clamp(2.4rem,8vw,4.5rem)] font-bold leading-[0.92] tracking-[-0.05em] sm:hidden">
            Explore our thinking
          </h2>
        </section>

        {/* ---------- SERVICES ---------- */}
        <section className="px-6 py-24 sm:px-10 lg:pl-52 lg:pr-16">
          <div className="mx-auto max-w-site lg:pl-40">
            <div className="max-w-[640px]">
              <h2 className="font-site-display text-[clamp(2.6rem,9vw,9.5rem)] leading-[0.9] font-bold tracking-[-0.05em]">
                What we bring to the Table
              </h2>
            </div>

            <div className="mt-16">
              {services.map((service) => (
                <div
                  key={service.name}
                  data-service-row
                  className="grid gap-10 border-t border-site-border py-14 lg:grid-cols-[1fr_1.15fr] lg:gap-20"
                >
                  <div>
                    <p className="text-sm font-medium tracking-[0.2em] text-site-secondary">
                      {service.number}
                    </p>
                    <h3 className="font-site-display mt-3 text-[clamp(2.4rem,6vw,6rem)] leading-[0.95] font-bold tracking-[-0.04em]">
                      {service.name}
                    </h3>
                  </div>
                  <div>
                    <h4 className="text-xl font-bold tracking-tight sm:text-2xl">
                      {service.subtitle}
                    </h4>
                    <p className="mt-4 text-base leading-relaxed text-site-ink/70 sm:text-lg">
                      {service.description}
                    </p>
                    <ul className="mt-8 grid gap-2.5 sm:grid-cols-2">
                      {service.list.map((item) => (
                        <li key={item} className="flex items-start gap-2.5 text-site-ink/70">
                          <span className="mt-2.5 h-1.5 w-1.5 shrink-0 rounded-full bg-site-accent ring-1 ring-site-ink/20" />
                          {item}
                        </li>
                      ))}
                    </ul>
                    <div className="mt-10">
                      <PillButton href={service.href}>
                        View Details
                        <ArrowRight className="h-4 w-4" />
                      </PillButton>
                    </div>
                  </div>
                </div>
              ))}
              <div className="border-t border-site-border" />
            </div>
          </div>
        </section>

        {/* ---------- TESTIMONIALS ---------- */}
        <section className="px-6 py-24 sm:px-10 lg:pl-52 lg:pr-16">
          <div className="mx-auto max-w-site lg:pl-40">
            <div className="flex flex-col items-start justify-between gap-6 sm:flex-row sm:items-end">
              <h2 className="font-site-display max-w-[640px] text-[clamp(2.6rem,7.5vw,7.5rem)] leading-[0.92] font-bold tracking-[-0.05em]">
                Real stories, real <span className="text-site-secondary">results</span>
              </h2>
              <Link
                href="/contact"
                className="inline-flex items-center gap-2 text-base font-bold sm:text-lg"
              >
                Contact Sales
                <ArrowRight className="h-4 w-4" />
              </Link>
            </div>

            <div className="mt-14 flex snap-x snap-mandatory gap-6 overflow-x-auto pb-4 [scrollbar-width:none] [&::-webkit-scrollbar]:hidden">
              {testimonials(appName).map((t) => (
                <article
                  key={t.name}
                  data-testimonial-item
                  className="flex min-h-[560px] w-[85vw] shrink-0 snap-start flex-col justify-between rounded-site-card bg-site-surface p-8 sm:w-[440px] sm:p-10"
                >
                  <p className="text-xl leading-relaxed text-site-ink/80 sm:text-2xl sm:leading-relaxed">
                    &ldquo;{t.quote}&rdquo;
                  </p>
                  <footer className="mt-10 flex items-center gap-4">
                    <img
                      src={t.avatar}
                      alt={`${t.name} portrait`}
                      loading="lazy"
                      className="h-16 w-16 rounded-full object-cover"
                    />
                    <div>
                      <p className="font-bold">{t.name}</p>
                      <p className="text-sm text-site-secondary">{t.role}</p>
                    </div>
                  </footer>
                </article>
              ))}
            </div>
          </div>
        </section>

        {/* ---------- FOOTER CTA ---------- */}
        <footer className="px-6 pt-16 sm:px-10 lg:pl-52 lg:pr-16">
          <div className="mx-auto max-w-site lg:pl-40">
            <nav className="flex flex-wrap items-center justify-between gap-4 border-b border-site-border pb-8 text-sm">
              <div className="flex gap-6">
                {["Instagram", "Twitter", "Behance"].map((social) => (
                  <a
                    key={social}
                    href="#"
                    className="text-site-ink/70 transition-colors hover:text-site-ink"
                  >
                    {social}
                  </a>
                ))}
              </div>
              <div className="flex gap-6">
                <a href="#" className="text-site-ink/70 transition-colors hover:text-site-ink">
                  Licensing
                </a>
                <Link
                  href={route("styleguide")}
                  className="text-site-ink/70 transition-colors hover:text-site-ink"
                >
                  Style Guide
                </Link>
                <a href="#" className="text-site-ink/70 transition-colors hover:text-site-ink">
                  Change log
                </a>
              </div>
              <p className="w-full text-sm text-site-ink-faint sm:w-auto">
                © {year} {appName}.
              </p>
            </nav>

            <div data-footer-cta className="relative py-24">
              <h2 className="font-site-display text-[clamp(2.8rem,10vw,10.5rem)] leading-[0.85] font-bold tracking-[-0.05em]">
                Create a brand that knows
                <br />
                where it&apos;s going.
              </h2>
              <h2
                data-footer-line-outline
                className="font-site-display mt-2 text-[clamp(2.8rem,10vw,10.5rem)] leading-[0.85] font-bold tracking-[-0.05em] text-transparent [-webkit-text-stroke:2px_var(--site-outline)]"
              >
                Create a brand that knows
                <br />
                where it&apos;s going.
              </h2>
              <Link
                href="/contact"
                aria-label="Let's Talk"
                className="absolute -top-2 right-4 flex h-[168px] w-[168px] items-center justify-center rounded-full bg-site-accent text-site-accent-ink transition-transform duration-200 hover:rotate-6 hover:scale-105"
              >
                <span className="rotate-[-24deg] text-center text-xs font-bold tracking-[0.2em] uppercase leading-relaxed">
                  Let&apos;s
                  <br />
                  Talk
                </span>
              </Link>
            </div>
          </div>
        </footer>
      </main>
    </div>
  );
}
