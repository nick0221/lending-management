import { Head, Link, useForm } from '@inertiajs/react';
import {
    AlertCircle,
    ArrowLeft,
    CheckCircle2,
    Download,
    Loader2,
    Upload,
} from 'lucide-react';
import { useState } from 'react';
import InputError from '@/components/input-error';
import { PageHeader } from '@/components/page-header';
import { SectionCard } from '@/components/section-card';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { dashboard as adminDashboard } from '@/routes/admin';
import { index as borrowersIndex } from '@/routes/admin/borrowers';
import {
    create as importCreate,
    errors as downloadImportErrors,
    store,
    template,
} from '@/routes/admin/borrowers/imports';
import type { BorrowerImportSummary } from '@/types';

const columns = [
    { name: 'name', description: 'Required. Full name.' },
    { name: 'phone', description: 'Required. Phone number.' },
    { name: 'national_id', description: 'Optional. Must be unique.' },
    { name: 'email', description: 'Optional. Used for notifications.' },
    {
        name: 'date_of_birth',
        description: 'Optional. Use YYYY-MM-DD.',
    },
    {
        name: 'address',
        description: 'Optional. Postal or residential address.',
    },
    {
        name: 'kyc_status',
        description: 'Optional. pending, verified or rejected.',
    },
    { name: 'notes', description: 'Optional. Internal notes.' },
];

export default function ImportBorrowers({
    summary,
}: {
    summary: BorrowerImportSummary | null;
}) {
    const [inputKey, setInputKey] = useState(0);
    const form = useForm<{ file: File | null }>({ file: null });

    const submit = (event: React.FormEvent<HTMLFormElement>): void => {
        event.preventDefault();

        form.post(store.url(), {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => {
                form.reset();
                setInputKey((key) => key + 1);
            },
        });
    };

    return (
        <>
            <Head title="Import borrowers" />

            <div className="mx-auto w-full max-w-6xl space-y-6 p-4">
                <PageHeader
                    title="Import borrowers"
                    description="Add existing borrowers in bulk from a CSV file."
                    actions={
                        <div className="flex flex-wrap items-center gap-2">
                            <Button variant="outline" asChild>
                                <a href={template.url()}>
                                    <Download /> Template
                                </a>
                            </Button>

                            <Button variant="outline" asChild>
                                <Link href={borrowersIndex()}>
                                    <ArrowLeft /> Back to borrowers
                                </Link>
                            </Button>
                        </div>
                    }
                />

                {summary && <ImportSummaryCard summary={summary} />}

                <div className="grid items-start gap-6 lg:grid-cols-[minmax(0,1fr)_22rem]">
                    <SectionCard
                        title="Upload CSV"
                        description="Choose a CSV file with a header row. Nothing is written until every row has been validated."
                        contentClassName="p-4"
                    >
                        <form onSubmit={submit} className="space-y-4">
                            <div className="grid gap-2">
                                <p className="text-sm leading-none font-medium">
                                    CSV file
                                </p>

                                <div className="flex flex-wrap items-center gap-3">
                                    <Button variant="outline" asChild>
                                        <label
                                            htmlFor="file"
                                            className="cursor-pointer"
                                        >
                                            <Upload /> Choose CSV file
                                            <input
                                                key={inputKey}
                                                id="file"
                                                name="file"
                                                type="file"
                                                accept=".csv,text/csv"
                                                className="sr-only"
                                                onChange={(event) =>
                                                    form.setData(
                                                        'file',
                                                        event.target
                                                            .files?.[0] ?? null,
                                                    )
                                                }
                                            />
                                        </label>
                                    </Button>

                                    <span className="text-sm text-muted-foreground">
                                        {form.data.file?.name ??
                                            'No file chosen'}
                                    </span>
                                </div>

                                <p className="text-xs text-muted-foreground">
                                    Up to 2 MB. UTF-8 with a header row.
                                </p>

                                <InputError message={form.errors.file} />
                            </div>

                            <Button
                                type="submit"
                                disabled={form.processing || !form.data.file}
                            >
                                {form.processing ? (
                                    <Loader2 className="animate-spin" />
                                ) : (
                                    <Upload />
                                )}
                                Import borrowers
                            </Button>
                        </form>
                    </SectionCard>

                    <div className="space-y-6">
                        <SectionCard
                            title="CSV columns"
                            description="Headers are matched by name. Extra columns are ignored."
                            contentClassName="p-4"
                        >
                            <dl className="space-y-3 text-sm">
                                {columns.map((column) => (
                                    <div
                                        key={column.name}
                                        className="grid gap-1"
                                    >
                                        <dt className="font-mono text-xs font-medium">
                                            {column.name}
                                        </dt>
                                        <dd className="text-muted-foreground">
                                            {column.description}
                                        </dd>
                                    </div>
                                ))}
                            </dl>
                        </SectionCard>

                        <SectionCard
                            title="How it works"
                            description="The importer is deliberately create-only."
                            contentClassName="p-4"
                        >
                            <ul className="list-disc space-y-2 pl-5 text-sm text-muted-foreground">
                                <li>
                                    Rows are validated before anything is
                                    written.
                                </li>
                                <li>
                                    Duplicate national IDs are skipped, not
                                    updated.
                                </li>
                                <li>
                                    Valid rows are created together; invalid
                                    rows are reported by line.
                                </li>
                            </ul>
                        </SectionCard>
                    </div>
                </div>
            </div>
        </>
    );
}

function ImportSummaryCard({ summary }: { summary: BorrowerImportSummary }) {
    const hasErrors = summary.error_count > 0;

    return (
        <SectionCard
            title="Last import"
            description={`${summary.imported} of ${summary.total_rows} rows imported.`}
            contentClassName="space-y-4 p-4"
            actions={
                hasErrors ? (
                    <Button size="sm" variant="outline" asChild>
                        <a href={downloadImportErrors.url()}>
                            <Download /> Download errors
                        </a>
                    </Button>
                ) : undefined
            }
        >
            <div className="grid gap-3 sm:grid-cols-3">
                <SummaryMetric label="Imported" value={summary.imported} />
                <SummaryMetric label="Skipped" value={summary.skipped} />
                <SummaryMetric label="Rows read" value={summary.total_rows} />
            </div>

            {hasErrors ? (
                <Alert variant="destructive">
                    <AlertCircle />
                    <AlertTitle>Some rows were skipped</AlertTitle>
                    <AlertDescription>
                        {summary.error_count}{' '}
                        {summary.error_count === 1
                            ? 'issue was'
                            : 'issues were'}{' '}
                        found.{' '}
                        {summary.error_count > summary.errors.length
                            ? `The table shows the first ${summary.errors.length}; download the CSV for all of them.`
                            : 'Review the rows below.'}
                    </AlertDescription>
                </Alert>
            ) : (
                <Alert>
                    <CheckCircle2 />
                    <AlertTitle>Import complete</AlertTitle>
                    <AlertDescription>
                        Every row was imported successfully.
                    </AlertDescription>
                </Alert>
            )}

            {summary.errors.length > 0 && (
                <div className="overflow-hidden rounded-lg border">
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead className="w-20">Line</TableHead>
                                <TableHead>Issue</TableHead>
                            </TableRow>
                        </TableHeader>

                        <TableBody>
                            {summary.errors.map((error) => (
                                <TableRow
                                    key={`${error.line}-${error.message}`}
                                >
                                    <TableCell className="font-mono text-xs text-muted-foreground">
                                        {error.line === 0 ? '—' : error.line}
                                    </TableCell>
                                    <TableCell>{error.message}</TableCell>
                                </TableRow>
                            ))}
                        </TableBody>
                    </Table>
                </div>
            )}
        </SectionCard>
    );
}

function SummaryMetric({ label, value }: { label: string; value: number }) {
    return (
        <div className="rounded-lg border p-3">
            <p className="text-xs text-muted-foreground">{label}</p>
            <p className="text-2xl font-semibold tabular-nums">{value}</p>
        </div>
    );
}

ImportBorrowers.layout = {
    breadcrumbs: [
        {
            title: 'Administration',
            href: adminDashboard(),
        },
        {
            title: 'Borrowers',
            href: borrowersIndex(),
        },
        {
            title: 'Import',
            href: importCreate(),
        },
    ],
};
