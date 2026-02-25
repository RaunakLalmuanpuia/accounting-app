import React, { useState, useEffect, useRef } from 'react';
import { Head, usePage, useForm } from '@inertiajs/react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import {
    Send, Sparkles, Paperclip, X, AlertCircle, Loader2, Copy, Check,
    FileText, BarChart3, Users, Receipt, ShieldCheck, Zap, Package, Building, Download
} from 'lucide-react';
import ReactMarkdown from 'react-markdown';
import remarkGfm from 'remark-gfm';

export default function Chat() {
    const { auth, errors } = usePage().props;

    const scrollRef = useRef(null);
    const fileInputRef = useRef(null);
    const textareaRef = useRef(null);

    const [localMessages, setLocalMessages] = useState([
        {
            id: 'welcome',
            role: 'assistant',
            content: "Hello! I'm your AI accounting assistant. How can I help you today?"
        }
    ]);

    const [copiedId, setCopiedId] = useState(null);

    const { data, setData, post, processing, reset, clearErrors } = useForm({
        message: '',
        conversation_id: null,
        attachments: []
    });

    useEffect(() => {
        if (auth.chatResponse && auth.chatResponse.reply) {
            setLocalMessages(prev => [...prev, {
                id: Date.now(),
                role: 'assistant',
                content: auth.chatResponse.reply
            }]);

            if (!data.conversation_id && auth.chatResponse.conversation_id) {
                setData('conversation_id', auth.chatResponse.conversation_id);
            }
        }
    }, [auth.chatResponse]);

    useEffect(() => {
        scrollRef.current?.scrollIntoView({ behavior: 'smooth' });
    }, [localMessages, processing]);

    const submit = (e) => {
        e?.preventDefault();
        if ((!data.message.trim() && data.attachments.length === 0) || processing) return;

        setLocalMessages(prev => [...prev, {
            id: Date.now() + 1,
            role: 'user',
            content: data.message.trim(),
            attachmentCount: data.attachments.length
        }]);

        post(route('accounting.chat.send'), {
            preserveScroll: true,
            preserveState: true,
            onSuccess: () => {
                reset('message', 'attachments');
                clearErrors();
                if(fileInputRef.current) fileInputRef.current.value = '';
                if (textareaRef.current) {
                    textareaRef.current.style.height = 'auto';
                }
            },
        });
    };

    const handleInput = (e) => {
        setData('message', e.target.value);
        if (textareaRef.current) {
            textareaRef.current.style.height = 'auto';
            textareaRef.current.style.height = `${Math.min(textareaRef.current.scrollHeight, 200)}px`;
        }
    };

    const handleKeyDown = (e) => {
        if (e.key === 'Enter' && !e.shiftKey) {
            e.preventDefault();
            submit(e);
        }
    };

    const handleFileChange = (e) => {
        const files = Array.from(e.target.files);
        if (data.attachments.length + files.length > 5) {
            alert("You can only attach up to 5 files at a time.");
            return;
        }
        setData('attachments', [...data.attachments, ...files]);
    };

    const removeAttachment = (index) => {
        const newAttachments = [...data.attachments];
        newAttachments.splice(index, 1);
        setData('attachments', newAttachments);
    };

    const handleCopy = (text, id) => {
        navigator.clipboard.writeText(text);
        setCopiedId(id);
        setTimeout(() => setCopiedId(null), 2000);
    };

    const suggestedPrompts = [
        "Fetch me an invoice",
        "Issue an invoice",
        "Give me 6 month report"
    ];

    const botFeatures = [
        { icon: <Building size={18} />, title: "Business Details", desc: "View your company profile, update business information, and manage settings." },
        { icon: <Users size={18} />, title: "Client Management", desc: "Look up client details, create new clients, update clients and delete clients." },
        { icon: <Package size={18} />, title: "Inventory Management", desc: "Look up inventory items, add new products, update stock levels, and delete items." },
        { icon: <FileText size={18} />, title: "Invoice Management", desc: "Draft new invoices, preview PDFs, fetch existing records, and manage your billing." },
    ];

    return (
        <AuthenticatedLayout>
            <Head title="Chat Assistant" />

            <div>
                <div className="max-w-6xl mx-auto py-8 px-4 sm:px-6 lg:px-8">

                    <div className="flex flex-col lg:flex-row gap-6 h-[80vh]">

                        {/* LEFT COLUMN: Chat Area */}
                        <div className="flex-1 bg-white overflow-hidden shadow-sm sm:rounded-2xl flex flex-col h-full border border-gray-100">
                            <header className="p-4 border-b border-gray-100 flex items-center justify-between bg-white/80 backdrop-blur-md z-10 sticky top-0">
                                <div className="flex items-center gap-3">
                                    <div className="relative">
                                        <div className="w-10 h-10 bg-gradient-to-br from-[#5d51e8] to-[#8e84f3] rounded-full flex items-center justify-center text-white font-bold text-sm tracking-wide shadow-sm">
                                            AI
                                        </div>
                                        <span className="absolute bottom-0 right-0 w-3 h-3 bg-green-500 border-2 border-white rounded-full"></span>
                                    </div>
                                    <div>
                                        <h1 className="font-bold text-gray-900 text-[15px]">Accounting Assistant</h1>
                                        <div className="flex items-center gap-1">
                                            <span className="w-2 h-2 bg-green-500 rounded-full"></span>
                                            <span className="text-xs text-gray-500 font-medium">Online</span>
                                        </div>
                                    </div>
                                </div>
                            </header>

                            {errors.ai && (
                                <div className="bg-red-50 p-3 flex items-center gap-2 text-red-700 text-sm border-b border-red-100">
                                    <AlertCircle size={16} />
                                    <span>{errors.ai}</span>
                                </div>
                            )}

                            <main className="flex-1 overflow-y-auto p-4 md:p-6 space-y-6 bg-gray-50/50">
                                <div className="flex justify-center">
                                    <span className="text-[10px] font-bold text-gray-400 bg-gray-100 px-3 py-1 rounded-full uppercase tracking-widest">
                                        Today
                                    </span>
                                </div>

                                {localMessages.map((msg) => (
                                    <div key={msg.id} className={`flex ${msg.role === 'user' ? 'justify-end' : 'justify-start'}`}>
                                        <div className={`relative max-w-[85%] px-5 py-3.5 rounded-2xl text-[15px] leading-relaxed shadow-sm group ${
                                            msg.role === 'user'
                                                ? 'bg-[#5d51e8] text-white rounded-br-sm'
                                                : 'bg-white border border-gray-100 text-gray-800 rounded-bl-sm'
                                        }`}>

                                            {msg.role === 'assistant' && (
                                                <button
                                                    onClick={() => handleCopy(msg.content, msg.id)}
                                                    className="absolute top-2 right-2 p-1.5 text-gray-400 hover:text-gray-700 hover:bg-gray-100 rounded-md opacity-0 group-hover:opacity-100 transition-opacity"
                                                    title="Copy to clipboard"
                                                >
                                                    {copiedId === msg.id ? <Check size={16} className="text-green-500" /> : <Copy size={16} />}
                                                </button>
                                            )}

                                            {msg.attachmentCount > 0 && (
                                                <div className="flex items-center gap-1 text-xs opacity-75 mb-2 pb-2 border-b border-white/20">
                                                    <Paperclip size={12} /> {msg.attachmentCount} file(s) attached
                                                </div>
                                            )}

                                            {msg.role === 'user' ? (
                                                <div className="whitespace-pre-wrap break-words">{msg.content}</div>
                                            ) : (
                                                <div className="markdown-body pr-6 break-words">
                                                    <ReactMarkdown
                                                        remarkPlugins={[remarkGfm]}
                                                        components={{
                                                            p: ({node, ...props}) => <p className="mb-2 last:mb-0" {...props} />,
                                                            ul: ({node, ...props}) => <ul className="list-disc ml-5 mb-2" {...props} />,
                                                            ol: ({node, ...props}) => <ol className="list-decimal ml-5 mb-2" {...props} />,
                                                            li: ({node, ...props}) => <li className="mb-1" {...props} />,
                                                            table: ({node, ...props}) => (
                                                                <div className="overflow-x-auto my-3">
                                                                    <table className="min-w-full divide-y divide-gray-200 border border-gray-200 text-sm" {...props} />
                                                                </div>
                                                            ),
                                                            th: ({node, ...props}) => <th className="bg-gray-50 px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider border-b border-gray-200" {...props} />,
                                                            td: ({node, ...props}) => <td className="px-3 py-2 whitespace-nowrap text-sm text-gray-700 border-b border-gray-200" {...props} />,
                                                            strong: ({node, ...props}) => <strong className="font-semibold text-gray-900" {...props} />,

                                                            /* --- NEW: Custom Anchor (Link) Renderer --- */
                                                            a: ({node, href, children, ...props}) => {
                                                                // Extract text to check if it says "PDF"
                                                                const textContent = Array.isArray(children) ? children.join('') : String(children);
                                                                const isPdf = href?.toLowerCase().includes('.pdf') || textContent.toLowerCase().includes('pdf');

                                                                if (isPdf) {
                                                                    return (
                                                                        <a
                                                                            href={href}
                                                                            target="_blank"
                                                                            rel="noopener noreferrer"
                                                                            className="inline-flex items-center gap-2 px-4 py-2 my-2 bg-indigo-50 text-indigo-700 rounded-xl border border-indigo-100 hover:bg-indigo-100 hover:shadow-sm transition-all text-sm font-semibold no-underline w-fit"
                                                                            {...props}
                                                                        >
                                                                            <Download size={16} className="text-indigo-500" />
                                                                            <span>{children}</span>
                                                                        </a>
                                                                    );
                                                                }

                                                                // Standard Link Format
                                                                return (
                                                                    <a
                                                                        href={href}
                                                                        target="_blank"
                                                                        rel="noopener noreferrer"
                                                                        className="text-[#5d51e8] hover:underline font-medium"
                                                                        {...props}
                                                                    >
                                                                        {children}
                                                                    </a>
                                                                );
                                                            }
                                                        }}
                                                    >
                                                        {msg.content}
                                                    </ReactMarkdown>
                                                </div>
                                            )}
                                        </div>
                                    </div>
                                ))}

                                {processing && (
                                    <div className="flex justify-start">
                                        <div className="bg-white border border-gray-100 text-gray-500 px-5 py-3.5 rounded-2xl rounded-bl-sm flex items-center gap-2 shadow-sm">
                                            <Loader2 size={16} className="animate-spin text-[#5d51e8]" />
                                            <span className="text-sm">Thinking...</span>
                                        </div>
                                    </div>
                                )}
                                <div ref={scrollRef} />
                            </main>

                            <footer className="p-4 bg-white border-t border-gray-100">

                                {data.attachments.length > 0 && (
                                    <div className="flex flex-wrap gap-2 mb-3">
                                        {data.attachments.map((file, idx) => (
                                            <div key={idx} className="flex items-center gap-2 bg-indigo-50 text-indigo-700 px-3 py-1.5 rounded-lg text-xs font-medium border border-indigo-100">
                                                <span className="truncate max-w-[120px]">{file.name}</span>
                                                <button type="button" onClick={() => removeAttachment(idx)} className="hover:text-red-500">
                                                    <X size={14} />
                                                </button>
                                            </div>
                                        ))}
                                    </div>
                                )}

                                {!data.attachments.length && localMessages.length <= 1 && (
                                    <div className="flex gap-2 overflow-x-auto no-scrollbar pb-3">
                                        {suggestedPrompts.map((text) => (
                                            <button
                                                key={text}
                                                onClick={() => {
                                                    setData('message', text);
                                                    setTimeout(() => submit(), 50);
                                                }}
                                                className="px-4 py-2 bg-white border border-gray-200 rounded-full text-sm font-medium text-gray-600 hover:bg-gray-50 hover:text-gray-900 transition-colors whitespace-nowrap"
                                            >
                                                {text}
                                            </button>
                                        ))}
                                    </div>
                                )}

                                <form onSubmit={submit} className="relative flex items-end bg-gray-100 rounded-3xl border border-transparent focus-within:ring-2 focus-within:ring-[#5d51e8]/20 focus-within:bg-white focus-within:border-[#5d51e8]/30 transition-all shadow-sm">

                                    <div className="absolute left-4 bottom-3.5 text-[#5d51e8]/60 pointer-events-none">
                                        <Sparkles size={20} />
                                    </div>

                                    <textarea
                                        ref={textareaRef}
                                        rows={1}
                                        placeholder="Message Accounting Assistant..."
                                        value={data.message}
                                        onChange={handleInput}
                                        onKeyDown={handleKeyDown}
                                        disabled={processing}
                                        className={`w-full pl-12 pr-24 py-3.5 bg-transparent border-none focus:ring-0 resize-none text-gray-800 placeholder-gray-500 m-0 ${errors.message ? 'border-red-300 ring-red-200 focus:ring-red-200' : ''}`}
                                        style={{
                                            minHeight: '52px',
                                            maxHeight: '200px'
                                        }}
                                    />

                                    <input
                                        type="file"
                                        multiple
                                        ref={fileInputRef}
                                        onChange={handleFileChange}
                                        className="hidden"
                                        accept=".pdf,.csv,.xlsx,.xls,.docx,.doc,.txt,.png,.jpg,.jpeg,.webp"
                                    />

                                    <div className="absolute right-2 bottom-2 flex items-center gap-1">
                                        <button
                                            type="button"
                                            onClick={() => fileInputRef.current?.click()}
                                            className="p-2 text-gray-400 hover:text-gray-700 hover:bg-gray-200 rounded-xl transition-colors"
                                            title="Attach files (Max 5)"
                                        >
                                            <Paperclip size={18} />
                                        </button>

                                        <button
                                            type="submit"
                                            disabled={processing || (!data.message.trim() && data.attachments.length === 0)}
                                            className="p-2 bg-[#5d51e8] text-white rounded-xl hover:bg-[#4a3fc4] transition-colors disabled:opacity-50 disabled:bg-gray-300 disabled:text-gray-500 disabled:cursor-not-allowed"
                                        >
                                            <Send size={18} className={data.message.trim() || data.attachments.length > 0 ? "translate-x-0.5" : ""} />
                                        </button>
                                    </div>
                                </form>

                                <div className="flex justify-between items-center px-2">
                                    {errors.message ? (
                                        <p className="text-red-500 text-xs mt-2">{errors.message}</p>
                                    ) : (
                                        <p className="text-gray-400 text-[10px] mt-2 text-center w-full">
                                            AI can make mistakes. Check important info.
                                        </p>
                                    )}
                                </div>

                            </footer>
                        </div>

                        {/* RIGHT COLUMN: Chat Functionalities Sidebar */}
                        <aside className="w-full lg:w-[320px] flex-shrink-0 bg-white overflow-hidden shadow-sm sm:rounded-2xl border border-gray-100 flex flex-col h-full">
                            <div className="p-5 border-b border-gray-100 bg-gray-50/50">
                                <h2 className="font-bold text-gray-900 flex items-center gap-2">
                                    <Sparkles size={18} className="text-[#5d51e8]" />
                                    What I can do
                                </h2>
                            </div>

                            <div className="flex-1 overflow-y-auto p-3 space-y-2">
                                {botFeatures.map((feature, idx) => (
                                    <div
                                        key={idx}
                                        className="flex gap-3 p-3 rounded-xl hover:bg-gray-50 transition-colors border border-transparent hover:border-gray-100 cursor-default"
                                    >
                                        <div className="flex-shrink-0 w-8 h-8 rounded-lg bg-indigo-50 text-indigo-600 flex items-center justify-center">
                                            {feature.icon}
                                        </div>
                                        <div>
                                            <h3 className="text-sm font-semibold text-gray-800">{feature.title}</h3>
                                            <p className="text-xs text-gray-500 mt-0.5 leading-relaxed">{feature.desc}</p>
                                        </div>
                                    </div>
                                ))}
                            </div>
                        </aside>

                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
