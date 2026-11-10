<?php if ($conversation): ?>
    <form
        id="chatForm"
        class="flex items-end gap-2 border-t border-slate-200 bg-white p-3 md:px-5 md:py-4"
        enctype="multipart/form-data"
    >
        <label
            for="chatAttachments"
            class="mb-1 cursor-pointer rounded-full p-2.5 text-slate-500 hover:bg-slate-100"
            aria-label="Joindre des fichiers"
        >
            <i data-lucide="paperclip" class="w-5 h-5"></i>
        </label>

        <input
            id="chatAttachments"
            name="attachments[]"
            type="file"
            multiple
            accept="image/jpeg,image/png,image/webp,image/gif,application/pdf,text/plain"
            class="sr-only"
        >

        <label class="flex flex-1 items-end rounded-2xl bg-slate-100 px-4 py-2.5 focus-within:ring-2 focus-within:ring-teal-500/30">
            <span class="sr-only">Ton message</span>
            <textarea
                name="content"
                maxlength="2000"
                rows="1"
                class="max-h-32 min-h-6 flex-1 resize-none bg-transparent text-sm leading-6 text-slate-800 outline-none placeholder:text-slate-400"
                placeholder="Ecris un message..."
            ></textarea>
        </label>

        <button
            type="submit"
            class="mb-0.5 grid h-11 w-11 shrink-0 place-items-center rounded-full bg-teal-700 text-white shadow-sm transition hover:bg-teal-800"
            aria-label="Envoyer le message"
        >
            <i data-lucide="send" class="w-4 h-4"></i>
        </button>

        <p
            id="chatAttachmentNames"
            class="ml-1 max-w-36 truncate text-[10px] text-slate-500"
            aria-live="polite"
        ></p>
    </form>
<?php endif; ?>
