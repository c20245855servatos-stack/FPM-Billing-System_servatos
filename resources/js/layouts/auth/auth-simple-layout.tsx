import AppLogoIcon from '@/components/app-logo-icon';
import { Link } from '@inertiajs/react';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';

interface AuthLayoutProps {
    children: React.ReactNode;
    name?: string;
    title?: string;
    description?: string;
}

export default function AuthSimpleLayout({ children, title, description }: AuthLayoutProps) {
    return (
        <div className="flex min-h-svh flex-col items-center justify-center bg-gradient-to-br from-slate-50 via-cyan-50/50 to-white p-6 md:p-10 dark:from-background dark:via-cyan-950/20 dark:to-background">
            <Card className="w-full max-w-md border-cyan-100 shadow-xl shadow-cyan-950/5 dark:border-cyan-900">
                <CardHeader className="items-center text-center">
                    <Link href={route('home')} className="mb-3 flex flex-col items-center gap-2 font-medium">
                        <div className="flex size-11 items-center justify-center rounded-full bg-cyan-600 text-white shadow-lg shadow-cyan-600/20">
                            <AppLogoIcon className="size-6 fill-current" />
                        </div>
                        <span className="sr-only">{title}</span>
                    </Link>
                    <CardTitle className="text-xl">{title}</CardTitle>
                    <CardDescription>{description}</CardDescription>
                </CardHeader>
                <CardContent>
                    {children}
                </CardContent>
            </Card>
        </div>
    );
}
