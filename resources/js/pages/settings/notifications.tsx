import { Form, Head } from '@inertiajs/react';
import { Mail, MessageSquare } from 'lucide-react';
import { useState } from 'react';
import NotificationController from '@/actions/App/Http/Controllers/Settings/NotificationController';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardFooter,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Label } from '@/components/ui/label';
import { Switch } from '@/components/ui/switch';

type PreferenceKey =
    | 'notify_due_soon'
    | 'notify_overdue'
    | 'notify_receipts'
    | 'notify_statements'
    | 'notify_sms';

type Preferences = Record<PreferenceKey, boolean> & {
    email: string | null;
    phone: string | null;
};

const emailPreferences: Array<{
    key: Exclude<PreferenceKey, 'notify_sms'>;
    label: string;
    description: string;
}> = [
    {
        key: 'notify_due_soon',
        label: 'Payment reminders',
        description: 'A reminder before an installment is due.',
    },
    {
        key: 'notify_overdue',
        label: 'Overdue notices',
        description: 'A notice after an installment falls past its due date.',
    },
    {
        key: 'notify_receipts',
        label: 'Payment receipts',
        description: 'A receipt whenever a payment is recorded.',
    },
    {
        key: 'notify_statements',
        label: 'Statements',
        description: 'A statement of your loan when the office sends one.',
    },
];

export default function NotificationSettings({
    preferences,
}: {
    preferences: Preferences;
}) {
    const [values, setValues] = useState<Preferences>(preferences);

    const setPreference = (key: PreferenceKey, checked: boolean) => {
        setValues((current) => ({ ...current, [key]: checked }));
    };

    return (
        <>
            <Head title="Notification settings" />

            <h1 className="sr-only">Notification settings</h1>

            <Form
                {...NotificationController.update.form()}
                options={{ preserveScroll: true }}
                className="space-y-6"
            >
                {({ errors, processing }) => (
                    <>
                        <Heading
                            variant="small"
                            title="Notifications"
                            description="Choose how the office keeps you up to date about your loans"
                        />

                        <Card>
                            <CardHeader>
                                <CardTitle className="flex items-center gap-2">
                                    <Mail className="h-4 w-4" /> Email
                                </CardTitle>
                                <CardDescription>
                                    {values.email
                                        ? `Sent to ${values.email}.`
                                        : 'No email address is on file. Ask the office to add one before email notifications can arrive.'}
                                </CardDescription>
                            </CardHeader>

                            <CardContent className="space-y-4">
                                {emailPreferences.map((preference) => (
                                    <div
                                        key={preference.key}
                                        className="flex items-start justify-between gap-4"
                                    >
                                        <div className="grid min-w-0 flex-1 gap-1">
                                            <Label htmlFor={preference.key}>
                                                {preference.label}
                                            </Label>
                                            <p className="text-xs text-muted-foreground">
                                                {preference.description}
                                            </p>
                                        </div>

                                        <Switch
                                            id={preference.key}
                                            checked={values[preference.key]}
                                            onCheckedChange={(checked) =>
                                                setPreference(
                                                    preference.key,
                                                    checked,
                                                )
                                            }
                                        />

                                        <input
                                            type="hidden"
                                            name={preference.key}
                                            value={
                                                values[preference.key]
                                                    ? '1'
                                                    : '0'
                                            }
                                        />
                                    </div>
                                ))}
                            </CardContent>
                        </Card>

                        <Card>
                            <CardHeader>
                                <CardTitle className="flex items-center gap-2">
                                    <MessageSquare className="h-4 w-4" /> Text
                                    messages
                                </CardTitle>
                                <CardDescription>
                                    {values.phone
                                        ? `Sent to ${values.phone}.`
                                        : 'No phone number is on file. Ask the office to add one before text messages can arrive.'}
                                </CardDescription>
                            </CardHeader>

                            <CardContent>
                                <div className="flex items-start justify-between gap-4">
                                    <div className="grid min-w-0 flex-1 gap-1">
                                        <Label htmlFor="notify_sms">
                                            Text message alerts
                                        </Label>
                                        <p className="text-xs text-muted-foreground">
                                            Applies to payment reminders,
                                            overdue notices and receipts.
                                            Statements stay on email because
                                            they carry the full schedule.
                                        </p>
                                    </div>

                                    <Switch
                                        id="notify_sms"
                                        checked={values.notify_sms}
                                        onCheckedChange={(checked) =>
                                            setPreference('notify_sms', checked)
                                        }
                                    />

                                    <input
                                        type="hidden"
                                        name="notify_sms"
                                        value={values.notify_sms ? '1' : '0'}
                                    />
                                </div>
                            </CardContent>

                            <CardFooter>
                                <InputError
                                    message={
                                        errors.notify_due_soon ??
                                        errors.notify_overdue ??
                                        errors.notify_receipts ??
                                        errors.notify_statements ??
                                        errors.notify_sms
                                    }
                                />
                            </CardFooter>
                        </Card>

                        <div className="flex items-center gap-4">
                            <Button
                                disabled={processing}
                                data-test="save-notifications"
                            >
                                Save preferences
                            </Button>
                        </div>
                    </>
                )}
            </Form>
        </>
    );
}
