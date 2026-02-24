import React, { useState, useEffect, useRef } from 'react';
import { Head, usePage, useForm } from '@inertiajs/react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout'; // <-- Import Breeze Layout
import { Send, Sparkles, Paperclip, X, AlertCircle, Loader2 } from 'lucide-react';

export default function Chat() {
    // Access props shared from the HandleInertiaRequests middleware
    const { auth, errors } = usePage().props;

    const scrollRef = useRef(null);
    const fileInputRef = useRef(null);

    const [localMessages, setLocalMessages] = useState([
        {
            id: 'welcome',
            role: 'assistant',
            content: "Hello! I'm your AI accounting assistant. How can I help you today?"
        }
    ]);

    const { data, setData, post, processing, reset, clearErrors } = useForm({
        message: '',
        conversation_id: null,
        attachments: []
    });

    // 1. Listen for new AI responses from flashed session data
    useEffect(() => {
        if (auth.chatResponse && auth.chatResponse.reply) {
            setLocalMessages(prev => [...prev, {
                id: Date.now(),
                role: 'assistant',
                content: auth.chatResponse.reply
            }]);

            // Save the conversation ID for subsequent requests
            if (!data.conversation_id && auth.chatResponse.conversation_id) {
                setData('conversation_id', auth.chatResponse.conversation_id);
            }
        }
    }, [auth.chatResponse]);

    // 2. Auto-scroll to the bottom when messages update
    useEffect(() => {
        scrollRef.current?.scrollIntoView({ behavior: 'smooth' });
    }, [localMessages, processing]);

    // 3. Handle Form Submission
    const submit = (e) => {
        e?.preventDefault();
        if ((!data.message.trim() && data.attachments.length === 0) || processing) return;

        setLocalMessages(prev => [...prev, {
            id: Date.now() + 1,
            role: 'user',
            content: data.message,
            attachmentCount: data.attachments.length
        }]);

        post(route('accounting.chat.send'), {
            preserveScroll: true,
            preserveState: true,
            onSuccess: () => {
                reset('message', 'attachments');
                clearErrors();
                if(fileInputRef.current) fileInputRef.current.value = '';
            },
        });
    };

    // 4. Handle File Attachments
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

    const suggestedPrompts = [
        "Fetch me an invoice",
        "Issue an invoice",
        "Give me 6 month report"
    ];

    return (
        <AuthenticatedLayout>
            <Head title="Chat Assistant" />

            <div>
                <div className="max-w-2xl mx-auto py-8 px-4 sm:px-6 lg:px-8 space-y-10">
                    {/* Notice the h-[75vh] here.
                        This gives the chat window a fixed height inside the Breeze layout
                        so the overflow-y-auto on the message area works properly.
                    */}
                    <div className="bg-white overflow-hidden shadow-sm sm:rounded-xl flex flex-col h-[75vh] border border-gray-100">

                        {/* Header */}
                        <header className="p-4 border-b border-gray-100 flex items-center justify-between bg-white z-10">
                            <div className="flex items-center gap-3">
                                <div className="relative">
                                    <div className="w-10 h-10 bg-[#5d51e8] rounded-full flex items-center justify-center text-white font-bold text-sm tracking-wide">
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

                        {/* Error Banner */}
                        {errors.ai && (
                            <div className="bg-red-50 p-3 flex items-center gap-2 text-red-700 text-sm border-b border-red-100">
                                <AlertCircle size={16} />
                                <span>{errors.ai}</span>
                            </div>
                        )}

                        {/* Messages Area */}
                        <main className="flex-1 overflow-y-auto p-4 md:p-6 space-y-6 bg-gray-50/30">
                            <div className="flex justify-center">
                                <span className="text-[10px] font-bold text-gray-400 bg-gray-100 px-3 py-1 rounded-full uppercase tracking-widest">
                                    Today
                                </span>
                            </div>

                            {localMessages.map((msg) => (
                                <div key={msg.id} className={`flex ${msg.role === 'user' ? 'justify-end' : 'justify-start'}`}>
                                    <div className={`max-w-[85%] px-5 py-3.5 rounded-2xl text-[15px] leading-relaxed shadow-sm ${
                                        msg.role === 'user'
                                            ? 'bg-[#5d51e8] text-white rounded-tr-none'
                                            : 'bg-white border border-gray-100 text-gray-800 rounded-tl-none'
                                    }`}>
                                        {msg.attachmentCount > 0 && (
                                            <div className="flex items-center gap-1 text-xs opacity-75 mb-2 pb-2 border-b border-white/20">
                                                <Paperclip size={12} /> {msg.attachmentCount} file(s) attached
                                            </div>
                                        )}
                                        <div className="whitespace-pre-wrap">{msg.content}</div>
                                    </div>
                                </div>
                            ))}

                            {/* Typing Indicator */}
                            {processing && (
                                <div className="flex justify-start">
                                    <div className="bg-gray-50 border border-gray-100 text-gray-500 px-5 py-3.5 rounded-2xl rounded-tl-none flex items-center gap-2 shadow-sm">
                                        <Loader2 size={16} className="animate-spin text-[#5d51e8]" />
                                        <span className="text-sm">Thinking...</span>
                                    </div>
                                </div>
                            )}
                            <div ref={scrollRef} />
                        </main>

                        {/* Footer / Input Area */}
                        <footer className="p-4 bg-white border-t border-gray-100">
                            {/* File Attachment Previews */}
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

                            {/* Suggested Prompts */}
                            {!data.attachments.length && (
                                <div className="flex gap-2 overflow-x-auto no-scrollbar pb-3">
                                    {suggestedPrompts.map((text) => (
                                        <button
                                            key={text}
                                            onClick={() => setData('message', text)}
                                            className="px-4 py-2 bg-white border border-gray-200 rounded-full text-sm font-medium text-gray-600 hover:bg-gray-50 hover:text-gray-900 transition-colors whitespace-nowrap"
                                        >
                                            {text}
                                        </button>
                                    ))}
                                </div>
                            )}

                            {/* Input Form */}
                            <form onSubmit={submit} className="relative group flex items-center gap-2">
                                <div className="relative flex-1 flex items-center">
                                    <div className="absolute left-4 text-[#5d51e8]/60">
                                        <Sparkles size={20} />
                                    </div>
                                    <input
                                        type="text"
                                        placeholder="Ask anything..."
                                        value={data.message}
                                        onChange={e => setData('message', e.target.value)}
                                        disabled={processing}
                                        className={`w-full pl-12 pr-14 py-4 bg-gray-100 border-transparent rounded-2xl focus:ring-2 focus:ring-[#5d51e8]/20 focus:bg-white focus:border-[#5d51e8]/30 transition-all text-gray-800 placeholder-gray-400 ${errors.message ? 'border-red-300 ring-red-200 focus:ring-red-200' : ''}`}
                                    />

                                    <input
                                        type="file"
                                        multiple
                                        ref={fileInputRef}
                                        onChange={handleFileChange}
                                        className="hidden"
                                        accept=".pdf,.csv,.xlsx,.xls,.docx,.doc,.txt,.png,.jpg,.jpeg,.webp"
                                    />

                                    <button
                                        type="button"
                                        onClick={() => fileInputRef.current?.click()}
                                        className="absolute right-14 p-2 text-gray-400 hover:text-gray-600 transition-colors"
                                        title="Attach files (Max 5)"
                                    >
                                        <Paperclip size={18} />
                                    </button>

                                    <button
                                        disabled={processing || (!data.message.trim() && data.attachments.length === 0)}
                                        className="absolute right-2 p-2.5 bg-[#8e84f3] text-white rounded-xl hover:bg-[#5d51e8] transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
                                    >
                                        <Send size={18} />
                                    </button>
                                </div>
                            </form>
                            {errors.message && <p className="text-red-500 text-xs mt-2 ml-4">{errors.message}</p>}
                            {errors['attachments.0'] && <p className="text-red-500 text-xs mt-2 ml-4">File upload error. Check file size/type.</p>}
                        </footer>
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
