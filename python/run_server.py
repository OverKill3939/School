import http.server
import os
import queue
import socketserver
import threading
import tkinter as tk
import webbrowser
from tkinter import filedialog, messagebox, ttk


class ThreadingTCPServer(socketserver.ThreadingMixIn, socketserver.TCPServer):
    allow_reuse_address = True


def create_handler(directory, log_queue):
    class Handler(http.server.SimpleHTTPRequestHandler):
        def __init__(self, *args, **kwargs):
            self._log_queue = log_queue
            super().__init__(*args, directory=directory, **kwargs)

        def log_message(self, fmt, *args):
            message = "%s - - [%s] %s" % (
                self.client_address[0],
                self.log_date_time_string(),
                fmt % args,
            )
            if getattr(self, "_log_queue", None):
                self._log_queue.put(message)
            else:
                super().log_message(fmt, *args)

    return Handler


class ServerApp:
    def __init__(self, root):
        self.root = root
        self.root.title("Local Server")
        self.root.geometry("760x500")
        self.root.minsize(680, 420)

        self.server = None
        self.thread = None
        self.log_queue = queue.Queue()

        self.directory_var = tk.StringVar(value=os.getcwd())
        self.port_var = tk.StringVar(value="8000")
        self.status_var = tk.StringVar(value="Stopped")

        self._setup_style()
        self._build_ui()
        self._poll_log()

        self.root.protocol("WM_DELETE_WINDOW", self.on_close)

    def _setup_style(self):
        self.root.configure(bg="#f1f5f9")
        self.root.option_add("*Font", ("Segoe UI", 10))

        style = ttk.Style(self.root)
        try:
            style.theme_use("clam")
        except tk.TclError:
            pass

        style.configure("TFrame", background="#f1f5f9")
        style.configure("TLabel", background="#f1f5f9", foreground="#0f172a")
        style.configure("Muted.TLabel", background="#f1f5f9",
                        foreground="#64748b")
        style.configure(
            "Card.TLabelframe",
            background="#ffffff",
            bordercolor="#dbe2ef",
            lightcolor="#dbe2ef",
            darkcolor="#dbe2ef",
        )
        style.configure(
            "Card.TLabelframe.Label",
            background="#ffffff",
            foreground="#0f172a",
            font=("Segoe UI", 10, "bold"),
        )
        style.configure("TEntry", fieldbackground="#ffffff")
        style.configure(
            "Accent.TButton",
            background="#2563eb",
            foreground="#ffffff",
            padding=(14, 6),
            bordercolor="#2563eb",
            focusthickness=0,
        )
        style.map(
            "Accent.TButton",
            background=[("active", "#1d4ed8"), ("pressed", "#1e40af")],
        )
        style.configure(
            "Ghost.TButton",
            background="#ffffff",
            foreground="#0f172a",
            padding=(14, 6),
            bordercolor="#cbd5f5",
            focusthickness=0,
        )
        style.map(
            "Ghost.TButton",
            background=[("active", "#eff6ff")],
            bordercolor=[("active", "#93c5fd")],
        )

    def _build_ui(self):
        main = ttk.Frame(self.root, padding=14)
        main.pack(fill="both", expand=True)

        header = ttk.Frame(main)
        header.pack(fill="x", pady=(0, 12))
        ttk.Label(header, text="Local Server", font=("Segoe UI", 14, "bold")).pack(
            side="left"
        )
        ttk.Label(header, textvariable=self.status_var, style="Muted.TLabel").pack(
            side="right"
        )

        config_frame = ttk.LabelFrame(
            main, text="Server Settings", padding=12, style="Card.TLabelframe"
        )
        config_frame.pack(fill="x")

        ttk.Label(config_frame, text="Directory:").grid(
            row=0, column=0, sticky="w")
        dir_entry = ttk.Entry(config_frame, textvariable=self.directory_var)
        dir_entry.grid(row=0, column=1, sticky="ew", padx=8)
        ttk.Button(
            config_frame, text="Browse", command=self.browse_dir, style="Ghost.TButton"
        ).grid(
            row=0, column=2, sticky="e"
        )

        ttk.Label(config_frame, text="Port:").grid(
            row=1, column=0, sticky="w", pady=(8, 0))
        port_entry = ttk.Entry(
            config_frame, textvariable=self.port_var, width=10)
        port_entry.grid(row=1, column=1, sticky="w", padx=8, pady=(8, 0))

        config_frame.columnconfigure(1, weight=1)

        button_frame = ttk.Frame(main, padding=(0, 12, 0, 0))
        button_frame.pack(fill="x")

        ttk.Button(
            button_frame, text="Start", command=self.start_server, style="Accent.TButton"
        ).pack(side="left")
        ttk.Button(
            button_frame, text="Stop", command=self.stop_server, style="Ghost.TButton"
        ).pack(side="left", padx=6)
        ttk.Button(
            button_frame,
            text="Open in Browser",
            command=self.open_in_browser,
            style="Ghost.TButton",
        ).pack(side="left", padx=6)

        log_frame = ttk.LabelFrame(
            main, text="Logs", padding=12, style="Card.TLabelframe")
        log_frame.pack(fill="both", expand=True, pady=(12, 0))

        self.log_text = tk.Text(
            log_frame,
            height=12,
            wrap="none",
            background="#0f172a",
            foreground="#e2e8f0",
            insertbackground="#e2e8f0",
            borderwidth=0,
            highlightthickness=0,
            font=("Consolas", 9),
        )
        self.log_text.pack(side="left", fill="both", expand=True)
        self.log_text.configure(state="disabled")

        scrollbar = ttk.Scrollbar(
            log_frame, orient="vertical", command=self.log_text.yview)
        scrollbar.pack(side="right", fill="y")
        self.log_text.configure(yscrollcommand=scrollbar.set)

    def browse_dir(self):
        directory = filedialog.askdirectory(
            initialdir=self.directory_var.get())
        if directory:
            self.directory_var.set(directory)

    def start_server(self):
        if self.server:
            messagebox.showinfo("Server", "Server is already running.")
            return

        directory = self.directory_var.get().strip()
        if not directory or not os.path.isdir(directory):
            messagebox.showerror("Error", "Please select a valid directory.")
            return

        try:
            port = int(self.port_var.get().strip())
        except ValueError:
            messagebox.showerror("Error", "Port must be a number.")
            return

        handler = create_handler(directory, self.log_queue)
        try:
            self.server = ThreadingTCPServer(("", port), handler)
        except OSError as exc:
            messagebox.showerror("Error", f"Failed to start server: {exc}")
            self.server = None
            return

        self.thread = threading.Thread(
            target=self.server.serve_forever, daemon=True)
        self.thread.start()
        self.status_var.set(f"Running on http://localhost:{port}")
        self._log(f"Serving: {directory}")

    def stop_server(self):
        if not self.server:
            return
        self.server.shutdown()
        self.server.server_close()
        self.server = None
        self.thread = None
        self.status_var.set("Stopped")
        self._log("Server stopped.")

    def open_in_browser(self):
        if not self.server:
            messagebox.showinfo("Server", "Server is not running.")
            return
        try:
            port = int(self.port_var.get().strip())
        except ValueError:
            port = 8000
        url = f"http://localhost:{port}/"
        webbrowser.open(url)

    def _log(self, message):
        self.log_queue.put(message)

    def _poll_log(self):
        try:
            while True:
                message = self.log_queue.get_nowait()
                self.log_text.configure(state="normal")
                self.log_text.insert("end", message + "\n")
                self.log_text.see("end")
                self.log_text.configure(state="disabled")
        except queue.Empty:
            pass
        self.root.after(120, self._poll_log)

    def on_close(self):
        self.stop_server()
        self.root.destroy()


def main():
    root = tk.Tk()
    app = ServerApp(root)
    root.mainloop()


if __name__ == "__main__":
    main()
