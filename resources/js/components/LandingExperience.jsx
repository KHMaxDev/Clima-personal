import { useEffect, useRef } from 'react';
import {
    ArrowRight,
    Bot,
    CalendarClock,
    CloudSun,
    ShieldCheck,
    Sparkles,
} from 'lucide-react';
import Reveal from './Reveal';
import { prefersReducedMotion, registerGsap } from '../lib/motion';

const FEATURES = [
    {
        icon: CloudSun,
        title: 'Pronóstico por hora',
        description: 'Temperatura, viento, lluvia, UV y más para cada franja de tu actividad.',
    },
    {
        icon: ShieldCheck,
        title: 'Evaluación determinista',
        description: 'Estado, puntaje y motivos reproducibles, regla por regla.',
    },
    {
        icon: Bot,
        title: 'Asistente IA',
        description: 'Explicaciones en lenguaje natural con fuentes de conocimiento (RAG).',
    },
];

export default function LandingExperience({
    authenticated = false,
    dashboardUrl = '/dashboard',
    registerUrl = '/register',
    loginUrl = '/login',
}) {
    const root = useRef(null);

    useEffect(() => {
        const reduceMotion = prefersReducedMotion();
        const { gsap } = registerGsap();

        const context = gsap.context(() => {
            if (reduceMotion) {
                return;
            }

            gsap.from('[data-hero]', {
                opacity: 0,
                y: 30,
                duration: 0.9,
                delay: 0.1,
                stagger: 0.12,
                ease: 'power3.out',
            });

            gsap.to('[data-parallax]', {
                yPercent: 20,
                ease: 'none',
                scrollTrigger: {
                    trigger: root.current,
                    start: 'top top',
                    end: 'bottom top',
                    scrub: 0.6,
                },
            });

            gsap.to('[data-scroll-cue]', {
                opacity: 0,
                scrollTrigger: {
                    trigger: root.current,
                    start: 'top -10%',
                    end: 'top -30%',
                    scrub: true,
                },
            });
        }, root);

        return () => context.revert();
    }, []);

    return (
        <section ref={root} className="relative overflow-hidden">
            <div className="pointer-events-none absolute inset-0 -z-10">
                <div
                    data-parallax
                    className="aurora-blob animate-aurora left-[-8%] top-[-12%] h-72 w-72 bg-indigo-400/40 dark:bg-indigo-500/30"
                />
                <div
                    data-parallax
                    className="aurora-blob animate-aurora right-[-6%] top-[6%] h-80 w-80 bg-sky-400/35 dark:bg-sky-500/25"
                    style={{ animationDelay: '-6s' }}
                />
                <div
                    data-parallax
                    className="aurora-blob animate-aurora bottom-[-16%] left-[30%] h-72 w-72 bg-violet-400/35 dark:bg-violet-500/25"
                    style={{ animationDelay: '-11s' }}
                />
                <div className="grid-overlay absolute inset-0" />
            </div>

            <div className="mx-auto max-w-4xl py-10 text-center sm:py-20">
                <span
                    data-hero
                    className="glass-glow inline-flex items-center gap-2 px-3 py-1 text-xs font-medium text-indigo-700 dark:text-indigo-300"
                >
                    <Sparkles className="h-3.5 w-3.5" strokeWidth={2} aria-hidden="true" />
                    Pronóstico + evaluación para tus actividades
                </span>

                <h1
                    data-hero
                    className="font-display mt-6 text-4xl font-bold tracking-tight sm:text-6xl"
                >
                    <span className="heading">Decide con el clima</span>{' '}
                    <span className="text-gradient">a tu favor</span>
                </h1>

                <p data-hero className="mx-auto mt-5 max-w-2xl text-base text-muted sm:text-lg">
                    ClimaQuest cruza tus actividades personales con el pronóstico de Open-Meteo y un
                    motor de reglas determinista para decirte si conviene salir, qué precauciones tomar
                    y cuándo es el mejor momento.
                </p>

                <div data-hero className="mt-9 flex flex-wrap items-center justify-center gap-3">
                    {authenticated ? (
                        <a href={dashboardUrl} className="btn-primary lift">
                            Ir al panel
                            <ArrowRight className="h-4 w-4" strokeWidth={2} aria-hidden="true" />
                        </a>
                    ) : (
                        <>
                            <a href={registerUrl} className="btn-primary lift">
                                Crear cuenta
                                <ArrowRight className="h-4 w-4" strokeWidth={2} aria-hidden="true" />
                            </a>
                            <a href={loginUrl} className="btn-secondary lift">
                                Iniciar sesión
                            </a>
                        </>
                    )}
                </div>

                <div
                    data-scroll-cue
                    className="mt-14 hidden items-center justify-center gap-2 text-xs text-muted sm:flex"
                    aria-hidden="true"
                >
                    <CalendarClock className="h-4 w-4" strokeWidth={1.75} />
                    Desliza para descubrir
                </div>
            </div>

            <div className="mx-auto grid max-w-5xl gap-4 pb-16 text-left sm:grid-cols-3">
                {FEATURES.map((feature, index) => {
                    const Icon = feature.icon;

                    return (
                        <Reveal
                            key={feature.title}
                            delay={index * 0.1}
                            className="glass-glow lift p-5"
                        >
                            <span className="inline-flex h-10 w-10 items-center justify-center rounded-xl bg-indigo-600/10 text-indigo-600 dark:bg-indigo-500/15 dark:text-indigo-300">
                                <Icon className="h-5 w-5" strokeWidth={1.9} aria-hidden="true" />
                            </span>
                            <h2 className="font-display mt-3 text-base font-semibold heading">
                                {feature.title}
                            </h2>
                            <p className="mt-1 text-sm text-muted">{feature.description}</p>
                        </Reveal>
                    );
                })}
            </div>
        </section>
    );
}
