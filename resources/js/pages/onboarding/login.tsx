import { Form, Head } from '@inertiajs/react';
import InputError from '@/components/input-error';
import PasswordInput from '@/components/password-input';
import TextLink from '@/components/text-link';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { store } from '@/routes/central/login';
import { request as forgotPassword } from '@/routes/central/password';
import { register } from '@/routes/onboarding';

type Props = {
    status?: string;
};

export default function Login({ status }: Props) {
    return (
        <>
            <Head title="Giriş yap" />

            {status && (
                <div className="mb-4 text-center text-sm font-medium text-success">
                    {status}
                </div>
            )}

            <Form
                {...store.form()}
                resetOnSuccess={['password']}
                disableWhileProcessing
                className="flex flex-col gap-6"
            >
                {({ processing, errors }) => (
                    <>
                        <div className="grid gap-2">
                            <Label htmlFor="email">E-posta</Label>
                            <Input
                                id="email"
                                name="email"
                                type="email"
                                required
                                autoFocus
                                autoComplete="email"
                                placeholder="ad@firma.com"
                            />
                            <InputError message={errors.email} />
                        </div>

                        <div className="grid gap-2">
                            <div className="flex items-center">
                                <Label htmlFor="password">Parola</Label>
                                <TextLink
                                    href={forgotPassword()}
                                    className="ml-auto text-sm"
                                >
                                    Parolamı unuttum
                                </TextLink>
                            </div>
                            <PasswordInput
                                id="password"
                                name="password"
                                required
                                autoComplete="current-password"
                                placeholder="Parola"
                            />
                            <InputError message={errors.password} />
                        </div>

                        <div className="flex items-center space-x-3">
                            <Checkbox id="remember" name="remember" />
                            <Label htmlFor="remember">Beni hatırla</Label>
                        </div>

                        <Button
                            type="submit"
                            className="w-full"
                            data-test="login-button"
                        >
                            {processing && <Spinner />}
                            Giriş yap
                        </Button>

                        <div className="text-center text-sm text-muted-foreground">
                            Henüz hesabınız yok mu?{' '}
                            <TextLink href={register()}>
                                Hesap oluşturun
                            </TextLink>
                        </div>
                    </>
                )}
            </Form>
        </>
    );
}
