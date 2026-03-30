import { login } from '@/routes';
import { store } from '@/routes/register';
import { Form, Head, Link, usePage } from '@inertiajs/react';

type SharedProps = {
    appLogoUrl?: string;
};

export default function Register() {
    const { appLogoUrl } = usePage<SharedProps>().props;

    return (
        <>
            <Head title="Register" />

            <div className="relative min-h-screen overflow-hidden bg-slate-950 text-slate-100 antialiased">
                <div className="absolute inset-0 bg-[radial-gradient(900px_circle_at_top,rgba(56,189,248,0.22),transparent_55%)]" />
                <div className="absolute inset-0 bg-[radial-gradient(700px_circle_at_bottom,rgba(30,64,175,0.25),transparent_60%)]" />
                <div className="absolute inset-0 bg-[linear-gradient(135deg,rgba(15,23,42,0.85),rgba(2,6,23,0.95))]" />

                <main className="relative flex min-h-screen items-center justify-center px-6 py-12">
                    <div className="w-full max-w-6xl">
                        <div className="grid gap-12 lg:grid-cols-[1.1fr_0.9fr] lg:items-center">
                            <section className="space-y-10">
                                <div className="flex items-center gap-4">
                                    <div className="flex w-40 items-center justify-center">
                                        <img
                                            src={appLogoUrl ?? '/branding/default-logo.svg'}
                                            alt="NexLoyal"
                                            className="w-auto"
                                        />
                                    </div>
                                </div>

                                <div className="space-y-4">
                                    <h1 className="text-3xl font-semibold tracking-tight text-slate-50 sm:text-4xl">
                                        Start building a loyalty program that
                                        feels personal.
                                    </h1>
                                    <p className="max-w-xl text-base text-slate-300">
                                        Create your NexLoyal workspace to
                                        launch tiers, points, coupons, and
                                        engagement flows with a clean admin
                                        experience.
                                    </p>
                                </div>

                                <div className="grid gap-4 sm:grid-cols-3">
                                    <div className="rounded-xl border border-slate-800 bg-slate-900/70 p-4 shadow-lg shadow-slate-950/40">
                                        <p className="text-xs uppercase tracking-[0.2em] text-slate-400">
                                            Launch faster
                                        </p>
                                        <p className="mt-2 text-lg font-semibold text-slate-50">
                                            Ready-made rewards
                                        </p>
                                        <p className="mt-1 text-xs text-slate-400">
                                            tiers, coupons, mystery boxes
                                        </p>
                                    </div>
                                    <div className="rounded-xl border border-slate-800 bg-slate-900/70 p-4 shadow-lg shadow-slate-950/40">
                                        <p className="text-xs uppercase tracking-[0.2em] text-slate-400">
                                            Engage better
                                        </p>
                                        <p className="mt-2 text-lg font-semibold text-slate-50">
                                            Chat and polls
                                        </p>
                                        <p className="mt-1 text-xs text-slate-400">
                                            keep customers coming back
                                        </p>
                                    </div>
                                    <div className="rounded-xl border border-slate-800 bg-slate-900/70 p-4 shadow-lg shadow-slate-950/40">
                                        <p className="text-xs uppercase tracking-[0.2em] text-slate-400">
                                            Track growth
                                        </p>
                                        <p className="mt-2 text-lg font-semibold text-slate-50">
                                            Insights and reports
                                        </p>
                                        <p className="mt-1 text-xs text-slate-400">
                                            understand repeat purchase behavior
                                        </p>
                                    </div>
                                </div>

                                <p className="text-sm text-slate-400">
                                    Use the same branded workspace your team
                                    sees after sign-in.
                                </p>
                            </section>

                            <section className="rounded-2xl border border-slate-800 bg-slate-900/85 p-6 shadow-2xl shadow-slate-950/60 backdrop-blur sm:p-8">
                                <div className="mb-6 space-y-2">
                                    <h2 className="text-2xl font-semibold text-slate-50">
                                        Sign up
                                    </h2>
                                    <p className="text-sm text-slate-300">
                                        Create your NexLoyal admin workspace.
                                    </p>
                                </div>

                                <Form
                                    {...store.form()}
                                    resetOnSuccess={[
                                        'password',
                                        'password_confirmation',
                                    ]}
                                    disableWhileProcessing
                                    className="space-y-5"
                                >
                                    {({ processing, errors }) => (
                                        <>
                                            {Object.keys(errors).length > 0 && (
                                                <div className="rounded-md border border-rose-500/40 bg-rose-500/10 px-4 py-3 text-sm text-rose-200">
                                                    <ul className="space-y-1">
                                                        {Object.values(errors).map(
                                                            (error, index) => (
                                                                <li
                                                                    key={`${error}-${index}`}
                                                                >
                                                                    {error}
                                                                </li>
                                                            ),
                                                        )}
                                                    </ul>
                                                </div>
                                            )}

                                            <div className="space-y-2">
                                                <label
                                                    htmlFor="name"
                                                    className="text-sm font-medium text-slate-100"
                                                >
                                                    Full name
                                                </label>
                                                <input
                                                    id="name"
                                                    type="text"
                                                    required
                                                    autoFocus
                                                    autoComplete="name"
                                                    name="name"
                                                    placeholder="Full name"
                                                    className="flex h-11 w-full rounded-md border border-slate-800 bg-slate-950/60 px-3 py-2 text-sm text-slate-100 shadow-sm transition-colors placeholder:text-slate-500 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-sky-400"
                                                />
                                            </div>

                                            <div className="space-y-2">
                                                <label
                                                    htmlFor="email"
                                                    className="text-sm font-medium text-slate-100"
                                                >
                                                    Email address
                                                </label>
                                                <input
                                                    id="email"
                                                    type="email"
                                                    required
                                                    autoComplete="email"
                                                    name="email"
                                                    placeholder="example@email.com"
                                                    className="flex h-11 w-full rounded-md border border-slate-800 bg-slate-950/60 px-3 py-2 text-sm text-slate-100 shadow-sm transition-colors placeholder:text-slate-500 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-sky-400"
                                                />
                                            </div>

                                            <div className="space-y-2">
                                                <label
                                                    htmlFor="password"
                                                    className="text-sm font-medium text-slate-100"
                                                >
                                                    Password
                                                </label>
                                                <input
                                                    id="password"
                                                    type="password"
                                                    required
                                                    autoComplete="new-password"
                                                    name="password"
                                                    placeholder="Create a password"
                                                    className="flex h-11 w-full rounded-md border border-slate-800 bg-slate-950/60 px-3 py-2 text-sm text-slate-100 shadow-sm transition-colors placeholder:text-slate-500 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-sky-400"
                                                />
                                            </div>

                                            <div className="space-y-2">
                                                <label
                                                    htmlFor="password_confirmation"
                                                    className="text-sm font-medium text-slate-100"
                                                >
                                                    Confirm password
                                                </label>
                                                <input
                                                    id="password_confirmation"
                                                    type="password"
                                                    required
                                                    autoComplete="new-password"
                                                    name="password_confirmation"
                                                    placeholder="Confirm your password"
                                                    className="flex h-11 w-full rounded-md border border-slate-800 bg-slate-950/60 px-3 py-2 text-sm text-slate-100 shadow-sm transition-colors placeholder:text-slate-500 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-sky-400"
                                                />
                                            </div>

                                            <button
                                                type="submit"
                                                disabled={processing}
                                                className="inline-flex h-11 w-full items-center justify-center rounded-md bg-sky-400 px-4 py-2 text-sm font-medium text-slate-950 shadow-lg shadow-sky-500/40 transition-colors hover:bg-sky-300 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-sky-300 disabled:cursor-not-allowed disabled:opacity-70"
                                            >
                                                {processing
                                                    ? 'Creating account...'
                                                    : 'Create account'}
                                            </button>
                                        </>
                                    )}
                                </Form>

                                <div className="mt-6 border-t border-slate-800 pt-5 text-center">
                                    <p className="text-sm text-slate-400">
                                        Already have an account?{' '}
                                        <Link
                                            href={login()}
                                            className="font-medium text-sky-300 transition-colors hover:text-sky-200"
                                        >
                                            Sign in
                                        </Link>
                                    </p>
                                </div>
                            </section>
                        </div>
                    </div>
                </main>
            </div>
        </>
    );
}
