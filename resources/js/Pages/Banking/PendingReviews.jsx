import React from 'react';
import { Head, useForm, usePage } from '@inertiajs/react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { MessageSquare, Upload, Loader2 } from 'lucide-react';
import PendingTransactionCard from '@/Components/Banking/PendingTransactionCard';

export default function PendingReviews({ auth, transactions, heads, bankAccounts = [] }) {
    const { flash } = usePage().props;

    // Filter transactions
    const pendingTransactions = transactions?.data?.filter(t => t.review_status === 'pending') || [];
    const completedTransactions = transactions?.data?.filter(t => t.review_status !== 'pending') || [];

    // --- SMS Ingest Form State ---
    const smsForm = useForm({
        bank_account_id: bankAccounts.length > 0 ? bankAccounts[0].id : '',
        raw_sms: '',
    });

    // --- Statement Upload Form State ---
    const statementForm = useForm({
        bank_account_id: bankAccounts.length > 0 ? bankAccounts[0].id : '',
        statement: null,
    });

    const handleSmsSubmit = (e) => {
        e.preventDefault();
        smsForm.post(route('banking.transactions.sms.ingest'), {
            onSuccess: () => smsForm.reset('raw_sms'),
        });
    };

    const handleStatementSubmit = (e) => {
        e.preventDefault();
        statementForm.post(route('banking.transactions.statement.upload'), {
            forceFormData: true,
            onSuccess: () => statementForm.reset('statement'),
        });
    };

    return (
        <AuthenticatedLayout user={auth.user}>
            <Head title="Pending Reviews" />

            <div className="max-w-2xl mx-auto py-8 px-4 sm:px-6 lg:px-8 space-y-10">

                {/* Global Success Message */}
                {flash?.success && (
                    <div className="bg-emerald-50 text-emerald-700 p-4 rounded-xl border border-emerald-200 text-sm font-medium">
                        {flash.success}
                    </div>
                )}

                {/* --- DATA ENTRY / QUICK ACTIONS SECTION --- */}
                <section className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    {/* Paste SMS Card */}
                    <div className="bg-white rounded-2xl shadow-sm border border-gray-100 p-5">
                        <div className="flex items-center gap-2 mb-4 text-indigo-600 font-semibold">
                            <MessageSquare size={18} />
                            <h2>Paste Bank SMS</h2>
                        </div>
                        <form onSubmit={handleSmsSubmit} className="space-y-3">
                            {/*<select*/}
                            {/*    value={smsForm.data.bank_account_id}*/}
                            {/*    onChange={(e) => smsForm.setData('bank_account_id', e.target.value)}*/}
                            {/*    className="w-full bg-gray-50 border border-gray-200 text-gray-800 rounded-lg px-3 py-2 text-sm outline-none focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500"*/}
                            {/*>*/}
                            {/*    {bankAccounts.map(acc => (*/}
                            {/*        <option key={acc.id} value={acc.id}>{acc.bank_name} - {acc.account_number}</option>*/}
                            {/*    ))}*/}
                            {/*</select>*/}
                            <textarea
                                placeholder="Paste raw SMS text here..."
                                value={smsForm.data.raw_sms}
                                onChange={(e) => smsForm.setData('raw_sms', e.target.value)}
                                className="w-full bg-gray-50 border border-gray-200 text-gray-800 rounded-lg px-3 py-2 text-sm min-h-[80px] outline-none focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500"
                            />
                            {smsForm.errors.raw_sms && <div className="text-red-500 text-xs">{smsForm.errors.raw_sms}</div>}

                            <button
                                disabled={smsForm.processing}
                                className="w-full bg-indigo-600 hover:bg-indigo-700 disabled:opacity-70 text-white font-medium py-2 rounded-lg text-sm transition-colors flex justify-center items-center gap-2"
                            >
                                {smsForm.processing && <Loader2 size={16} className="animate-spin" />}
                                Process SMS
                            </button>
                        </form>
                    </div>

                    {/* Upload Statement Card */}
                    <div className="bg-white rounded-2xl shadow-sm border border-gray-100 p-5">
                        <div className="flex items-center gap-2 mb-4 text-indigo-600 font-semibold">
                            <Upload size={18} />
                            <h2>Upload Statement</h2>
                        </div>
                        <form onSubmit={handleStatementSubmit} className="space-y-3">
                            {/*<select*/}
                            {/*    value={statementForm.data.bank_account_id}*/}
                            {/*    onChange={(e) => statementForm.setData('bank_account_id', e.target.value)}*/}
                            {/*    className="w-full bg-gray-50 border border-gray-200 text-gray-800 rounded-lg px-3 py-2 text-sm outline-none focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500"*/}
                            {/*>*/}
                            {/*    {bankAccounts.map(acc => (*/}
                            {/*        <option key={acc.id} value={acc.id}>{acc.bank_name} - {acc.account_number}</option>*/}
                            {/*    ))}*/}
                            {/*</select>*/}

                            <input
                                type="file"
                                accept=".pdf,.csv,.xls,.xlsx,.png,.jpg,.jpeg"
                                onChange={(e) => statementForm.setData('statement', e.target.files[0])}
                                className="w-full bg-gray-50 border border-gray-200 text-gray-800 rounded-lg px-3 py-1.5 text-sm file:mr-4 file:py-1 file:px-3 file:rounded-md file:border-0 file:text-xs file:font-semibold file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100 outline-none focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500"
                            />
                            {statementForm.errors.statement && <div className="text-red-500 text-xs">{statementForm.errors.statement}</div>}

                            <button
                                disabled={statementForm.processing || !statementForm.data.statement}
                                className="w-full bg-indigo-600 hover:bg-indigo-700 disabled:opacity-70 text-white font-medium py-2 rounded-lg text-sm transition-colors mt-auto flex justify-center items-center gap-2"
                            >
                                {statementForm.processing && <Loader2 size={16} className="animate-spin" />}
                                Upload File
                            </button>
                        </form>
                    </div>
                </section>

                {/* --- PENDING REVIEW SECTION --- */}
                {pendingTransactions.length > 0 && (
                    <section>
                        <div className="flex items-center gap-3 mb-6 mt-10">
                            <div className="w-1.5 h-6 bg-[#4F46E5] rounded-full"></div>
                            <h1 className="text-lg font-bold text-[#1E293B] tracking-tight flex items-center gap-3">
                                PENDING REVIEW
                                <span className="bg-[#E0E7FF] text-[#4338CA] text-xs py-0.5 px-2.5 rounded-full font-bold">
                                    {pendingTransactions.length}
                                </span>
                            </h1>
                        </div>

                        <div className="space-y-6">
                            {pendingTransactions.map((transaction) => (
                                <PendingTransactionCard
                                    key={transaction.id}
                                    transaction={transaction}
                                    heads={heads}
                                />
                            ))}
                        </div>
                    </section>
                )}

                {/* --- RECENTLY COMPLETED SECTION --- */}
                {completedTransactions.length > 0 && (
                    <section>
                        <div className="flex items-center gap-3 mb-6 mt-12">
                            <div className="w-1.5 h-6 bg-[#10B981] rounded-full"></div>
                            <h1 className="text-lg font-bold text-[#1E293B] tracking-tight flex items-center gap-3">
                                RECENTLY COMPLETED
                            </h1>
                        </div>

                        <div className="space-y-6">
                            {completedTransactions.map((transaction) => (
                                <PendingTransactionCard
                                    key={transaction.id}
                                    transaction={transaction}
                                    heads={heads}
                                />
                            ))}
                        </div>
                    </section>
                )}
            </div>
        </AuthenticatedLayout>
    );
}
