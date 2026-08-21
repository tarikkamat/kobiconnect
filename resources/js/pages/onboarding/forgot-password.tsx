import { Form, Head } from '@inertiajs/react';
import InputError from '@/components/input-error';
import TextLink from '@/components/text-link';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { login } from '@/routes/central';
import { email as sendResetLink } from '@/routes/central/password';

type Props = {
    status?: string;
};

export default function ForgotPassword({ status }: Props) {
    return (
        <>
            <Head title="Parolamı unuttum" />

            {status && (
                <div className="mb-4 text-center text-sm font-medium text-green-600">
                    {status}
                </div>
            )}

            <Form
                {...sendResetLink.form()}
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

                        <Button type="submit" className="w-full">
                            {processing && <Spinner />}
                            Sıfırlama bağlantısı gönder
                        </Button>

                        <div className="text-center text-sm text-muted-foreground">
                            Parolanızı hatırladınız mı?{' '}
                            <TextLink href={login()}>Giriş yapın</TextLink>
                        </div>
                    </>
                )}
            </Form>
        </>
    );
}

ForgotPassword.layout = {
    title: 'Parolanızı sıfırlayın',
    description:
        'E-posta adresinizi girin, sıfırlama bağlantısını size gönderelim',
};
