import { Form, Head, Link, router, usePage } from '@inertiajs/react';
import {
    CheckCircle2,
    ClipboardCheck,
    Clock,
    Download,
    Loader2,
    RotateCcw,
    ShieldCheck,
    XCircle,
} from 'lucide-react';
import { useState } from 'react';
import { DataPagination } from '@/components/data-pagination';
import { EmptyState } from '@/components/empty-state';
import { FilterTabs } from '@/components/filter-tabs';
import InputError from '@/components/input-error';
import { PageHeader } from '@/components/page-header';
import { SearchInput } from '@/components/search-input';
import StatCard from '@/components/stat-card';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { formatMoney, formatRelativeTime, formatDateTime } from '@/lib/format';
import {
    approve as approveRequest,
    exportMethod as exportApprovals,
    index as approvalsIndex,
    reject as rejectRequest,
} from '@/routes/admin/approvals';
import { show as showLoan } from '@/routes/admin/loans';
import type { ApprovalRow, ApprovalType, Paginated } from '@/types';

const textareaClassName =
    'border-input placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-ring/50 aria-invalid:border-destructive aria-invalid:ring-destructive/20 flex min-h-20 w-full rounded-md border bg-transparent px-3 py-2 text-base shadow-xs transition-[color,box-shadow] outline-none focus-visible:ring-[3px] md:text-sm';

const statusTone: Record<ApprovalRow['status'], string> = {
    pending: 'bg-amber-500/10 text-amber-700 dark:text-amber-400',
    approved: 'bg-emerald-500/10 text-emerald-700 dark:text-emerald-400',
    rejected: 'bg-red-500/10 text-red-700 dark:text-red-400',
};

type Decision = {
    approval: ApprovalRow;
    action: 'approve' | 'reject';
};

export default function ApprovalsIndex({
    approvals,
    counts,
    currentTab,
    currentType,
    types,
    search,
}: {
    approvals: Paginated<ApprovalRow>;
    counts: Record<'pending' | 'approved' | 'rejected' | 'all', number>;
    currentTab: 'pending' | 'approved' | 'rejected' | 'all';
    currentType: ApprovalType | null;
    types: { value: ApprovalType; label: string }[];
    search: string;
}) {
    const [decision, setDecision] = useState<Decision | null>(null);
    const { url } = usePage();
    const perPage = new URL(url, 'http://localhost').searchParams.get(
        'per_page',
    );

    const withFilters = (overrides: {
        tab?: string;
        type?: ApprovalType | null;
        search?: string | null;
    }) => {
        const tab = overrides.tab ?? currentTab;
        const type =
            overrides.type !== undefined ? overrides.type : currentType;
        const term = overrides.search !== undefined ? overrides.search : search;

        return approvalsIndex({
            query: {
                ...(tab !== 'pending' ? { tab } : {}),
                ...(type ? { type } : {}),
                ...(term ? { search: term } : {}),
                ...(perPage && Number(perPage) !== 15
                    ? { per_page: Number(perPage) }
                    : {}),
            },
        });
    };

    const changeType = (value: string) => {
        router.get(
            withFilters({
                type: value === 'all' ? null : (value as ApprovalType),
            }).url,
            {},
            { preserveScroll: true, preserveState: true },
        );
    };

    const clearFilters = approvalsIndex({
        query:
            perPage && Number(perPage) !== 15
                ? { per_page: Number(perPage) }
                : {},
    });

    const hasFilters =
        search !== '' || currentType !== null || currentTab !== 'pending';

    const exportUrl = exportApprovals({
        query: {
            ...(currentTab !== 'pending' ? { tab: currentTab } : {}),
            ...(currentType ? { type: currentType } : {}),
            ...(search ? { search } : {}),
        },
    });

    return (
        <>
            <Head title="Approvals" />

            <div className="flex h-full flex-1 flex-col gap-6 p-4">
                <PageHeader
                    title="Approvals"
                    description="Financially sensitive actions wait here until a second administrator signs off. Nobody can decide their own request."
                    actions={
                        <Button variant="outline" asChild>
                            <a href={exportUrl.url}>
                                <Download /> Export CSV
                            </a>
                        </Button>
                    }
                />

                <div className="grid gap-4 sm:grid-cols-3">
                    <StatCard
                        title="Awaiting approval"
                        value={counts.pending}
                        hint="Needs a second signature"
                        icon={Clock}
                        tone={counts.pending > 0 ? 'warning' : 'default'}
                    />
                    <StatCard
                        title="Approved"
                        value={counts.approved}
                        hint="Actions already carried out"
                        icon={CheckCircle2}
                        tone="success"
                    />
                    <StatCard
                        title="Rejected"
                        value={counts.rejected}
                        hint="Declined without changes"
                        icon={XCircle}
                    />
                </div>

                <div className="flex flex-wrap items-center gap-3">
                    <SearchInput
                        value={search}
                        placeholder="Search borrower, phone, loan or request ID"
                        label="Search approvals"
                    />

                    <Select
                        value={currentType ?? 'all'}
                        onValueChange={changeType}
                    >
                        <SelectTrigger
                            className="h-9 min-w-0 flex-1 sm:w-[190px] sm:flex-none"
                            aria-label="Filter by request type"
                        >
                            <SelectValue placeholder="All request types">
                                {currentType
                                    ? types.find(
                                          (type) => type.value === currentType,
                                      )?.label
                                    : 'All request types'}
                            </SelectValue>
                        </SelectTrigger>

                        <SelectContent>
                            <SelectItem value="all">
                                All request types
                            </SelectItem>

                            {types.map((type) => (
                                <SelectItem key={type.value} value={type.value}>
                                    {type.label}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>

                    {currentType && (
                        <Button
                            variant="ghost"
                            size="sm"
                            onClick={() =>
                                router.get(
                                    withFilters({ type: null }).url,
                                    {},
                                    { preserveScroll: true },
                                )
                            }
                        >
                            <RotateCcw /> Clear type
                        </Button>
                    )}
                </div>

                <FilterTabs
                    tabs={[
                        {
                            value: 'pending',
                            label: 'Pending',
                            count: counts.pending,
                            href: withFilters({ tab: 'pending' }),
                        },
                        {
                            value: 'approved',
                            label: 'Approved',
                            count: counts.approved,
                            href: withFilters({ tab: 'approved' }),
                        },
                        {
                            value: 'rejected',
                            label: 'Rejected',
                            count: counts.rejected,
                            href: withFilters({ tab: 'rejected' }),
                        },
                        {
                            value: 'all',
                            label: 'All',
                            count: counts.all,
                            href: withFilters({ tab: 'all' }),
                        },
                    ]}
                    value={currentTab}
                    label="Filter approvals by status"
                    className="min-w-0"
                />

                <div className="overflow-hidden rounded-xl border border-sidebar-border/70 dark:border-sidebar-border">
                    {approvals.data.length === 0 ? (
                        <EmptyState
                            icon={ClipboardCheck}
                            title={
                                currentTab === 'pending'
                                    ? 'Nothing waiting for a decision'
                                    : 'No requests found'
                            }
                            description={
                                currentTab === 'pending'
                                    ? 'Write-off and reversal requests that need a second signature appear here.'
                                    : 'No requests match these filters. Try another status or request type.'
                            }
                            action={
                                hasFilters && (
                                    <Button asChild size="sm" variant="outline">
                                        <Link href={clearFilters}>
                                            <RotateCcw /> Clear filters
                                        </Link>
                                    </Button>
                                )
                            }
                        />
                    ) : (
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Request</TableHead>
                                    <TableHead className="hidden lg:table-cell">
                                        Reason
                                    </TableHead>
                                    <TableHead className="hidden md:table-cell">
                                        Requested
                                    </TableHead>
                                    <TableHead className="text-right">
                                        Amount
                                    </TableHead>
                                    <TableHead>Status</TableHead>
                                    <TableHead className="text-right">
                                        <span className="sr-only">Actions</span>
                                    </TableHead>
                                </TableRow>
                            </TableHeader>

                            <TableBody>
                                {approvals.data.map((approval) => (
                                    <TableRow key={approval.id}>
                                        <TableCell>
                                            <div className="flex min-w-0 flex-col gap-1">
                                                <div className="flex flex-wrap items-center gap-2">
                                                    <Badge
                                                        variant="outline"
                                                        className={
                                                            approval.type ===
                                                            'loan_write_off'
                                                                ? 'border-amber-500/40 text-amber-700 dark:text-amber-400'
                                                                : 'border-sky-500/40 text-sky-700 dark:text-sky-400'
                                                        }
                                                    >
                                                        {approval.type_label}
                                                    </Badge>

                                                    <Link
                                                        href={showLoan(
                                                            approval.loan_id,
                                                        )}
                                                        className="text-sm font-medium hover:underline"
                                                        preserveScroll
                                                    >
                                                        Loan #{approval.loan_id}
                                                    </Link>
                                                </div>

                                                <span className="truncate text-sm text-muted-foreground">
                                                    {approval.borrower_name}
                                                </span>
                                            </div>
                                        </TableCell>

                                        <TableCell className="hidden max-w-[22rem] lg:table-cell">
                                            <span className="line-clamp-2 text-sm text-muted-foreground">
                                                {approval.reason}
                                            </span>
                                        </TableCell>

                                        <TableCell className="hidden md:table-cell">
                                            <div className="flex flex-col text-sm">
                                                <span>
                                                    {approval.requested_by ??
                                                        'Unknown'}
                                                </span>
                                                <span className="text-xs text-muted-foreground">
                                                    {formatRelativeTime(
                                                        approval.requested_at,
                                                    )}
                                                </span>
                                            </div>
                                        </TableCell>

                                        <TableCell className="text-right font-medium tabular-nums">
                                            {formatMoney(approval.amount)}
                                        </TableCell>

                                        <TableCell>
                                            <div className="flex flex-col gap-1">
                                                <Badge
                                                    variant="secondary"
                                                    className={
                                                        statusTone[
                                                            approval.status
                                                        ]
                                                    }
                                                >
                                                    {approval.status ===
                                                        'pending' && (
                                                        <Clock className="size-3" />
                                                    )}
                                                    {approval.status ===
                                                        'approved' && (
                                                        <CheckCircle2 className="size-3" />
                                                    )}
                                                    {approval.status ===
                                                        'rejected' && (
                                                        <XCircle className="size-3" />
                                                    )}
                                                    {approval.status_label}
                                                </Badge>

                                                {approval.status !==
                                                    'pending' &&
                                                    approval.reviewed_by && (
                                                        <span className="hidden max-w-[14rem] text-xs text-muted-foreground lg:block">
                                                            {
                                                                approval.reviewed_by
                                                            }
                                                            {approval.reviewed_at
                                                                ? ` \u00b7 ${formatDateTime(approval.reviewed_at)}`
                                                                : ''}
                                                        </span>
                                                    )}
                                            </div>
                                        </TableCell>

                                        <TableCell className="text-right">
                                            {approval.can_decide ? (
                                                <div className="flex justify-end gap-2">
                                                    <Button
                                                        size="sm"
                                                        variant="outline"
                                                        onClick={() =>
                                                            setDecision({
                                                                approval,
                                                                action: 'approve',
                                                            })
                                                        }
                                                        data-test={`approve-${approval.id}`}
                                                    >
                                                        <CheckCircle2 /> Approve
                                                    </Button>

                                                    <Button
                                                        size="sm"
                                                        variant="ghost"
                                                        className="text-destructive hover:text-destructive"
                                                        onClick={() =>
                                                            setDecision({
                                                                approval,
                                                                action: 'reject',
                                                            })
                                                        }
                                                        data-test={`reject-${approval.id}`}
                                                    >
                                                        <XCircle /> Reject
                                                    </Button>
                                                </div>
                                            ) : approval.status === 'pending' &&
                                              approval.is_requester ? (
                                                <span className="inline-flex items-center gap-1 text-xs text-muted-foreground">
                                                    <ShieldCheck className="size-3.5" />
                                                    Your request
                                                </span>
                                            ) : (
                                                <span className="text-xs text-muted-foreground">
                                                    Decided
                                                </span>
                                            )}
                                        </TableCell>
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>
                    )}
                </div>

                <DataPagination paginator={approvals} />
            </div>

            <DecisionDialog
                decision={decision}
                onOpenChange={(open) => {
                    if (!open) {
                        setDecision(null);
                    }
                }}
            />
        </>
    );
}

/**
 * Confirm a decision and optionally record why. Approving runs the guarded
 * action; rejecting leaves the loan untouched.
 */
function DecisionDialog({
    decision,
    onOpenChange,
}: {
    decision: Decision | null;
    onOpenChange: (open: boolean) => void;
}) {
    const approving = decision?.action === 'approve';
    const approval = decision?.approval;
    const form = approval
        ? approving
            ? approveRequest(approval.id)
            : rejectRequest(approval.id)
        : null;

    return (
        <Dialog open={decision !== null} onOpenChange={onOpenChange}>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>
                        {approving
                            ? 'Approve this request?'
                            : 'Reject this request?'}
                    </DialogTitle>

                    <DialogDescription>
                        {approval && (
                            <>
                                {approving
                                    ? approval.type === 'loan_write_off'
                                        ? 'The loan is written off immediately and closes.'
                                        : 'The payment is reversed immediately and its allocations are released.'
                                    : 'The request is closed without changing the loan or payment.'}{' '}
                                {approval.type_label.toLowerCase()} of{' '}
                                {formatMoney(approval.amount)} for{' '}
                                {approval.borrower_name} (loan #
                                {approval.loan_id}).
                            </>
                        )}
                    </DialogDescription>
                </DialogHeader>

                {form && (
                    <Form
                        {...form}
                        options={{ preserveScroll: true }}
                        onSuccess={() => onOpenChange(false)}
                    >
                        {({ processing, errors }) => (
                            <>
                                <div className="grid gap-2">
                                    <Label htmlFor="approval-note">
                                        Note (optional)
                                    </Label>

                                    <textarea
                                        id="approval-note"
                                        name="note"
                                        maxLength={500}
                                        placeholder={
                                            approving
                                                ? 'Anything the requester should know.'
                                                : 'Why this request was declined.'
                                        }
                                        className={textareaClassName}
                                    />

                                    <InputError
                                        message={errors.note ?? errors.approval}
                                    />
                                </div>

                                <DialogFooter className="mt-4">
                                    <DialogClose asChild>
                                        <Button
                                            type="button"
                                            variant="secondary"
                                        >
                                            Cancel
                                        </Button>
                                    </DialogClose>

                                    <Button
                                        type="submit"
                                        variant={
                                            approving
                                                ? 'default'
                                                : 'destructive'
                                        }
                                        disabled={processing}
                                    >
                                        {processing ? (
                                            <Loader2 className="animate-spin" />
                                        ) : approving ? (
                                            <CheckCircle2 />
                                        ) : (
                                            <XCircle />
                                        )}
                                        {approving ? 'Approve' : 'Reject'}
                                    </Button>
                                </DialogFooter>
                            </>
                        )}
                    </Form>
                )}
            </DialogContent>
        </Dialog>
    );
}
