import React, { useEffect, useState } from "react";
import { Head } from "@inertiajs/react";

export default function M_TargetSelector() {
    const [projects, setProjects] = useState([]);
    const [basePath, setBasePath] = useState("");
    const [selected, setSelected] = useState("");
    const [error, setError] = useState("");
    const [busy, setBusy] = useState(false);

    const scan = async (base = "") => {
        setError("");
        const url = base
            ? `/api/target/scan?base=${encodeURIComponent(base)}`
            : "/api/target/scan";
        const res = await fetch(url);
        const data = await res.json();
        if (!data.success) {
            setError(data.message);
            return;
        }
        setBasePath(data.base);
        setProjects(data.projects);
    };

    useEffect(() => {
        scan();
    }, []);

    const save = async () => {
        setBusy(true);
        setError("");
        const res = await fetch("/api/target/save", {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify({ path: selected }),
        });
        const data = await res.json();
        setBusy(false);
        if (!data.success) {
            setError(data.message);
            return;
        }
        window.location.href = "/dashboard";
    };

    return (
        <div className="flex flex-col items-center justify-center min-h-screen bg-gray-100">
            <Head title="Select Target Application" />
            <div className="w-[520px] p-8 bg-white rounded-lg shadow-md">
                <h2 className="mb-1 text-xl font-bold text-gray-800">
                    Target Application Manager
                </h2>
                <p className="mb-5 text-sm text-gray-500">
                    Choose a target laravel project to start M-Project
                </p>

                <div className="flex gap-2 mb-4">
                    <input
                        className="flex-1 px-3 py-2 text-sm border rounded"
                        value={basePath}
                        onChange={(e) => setBasePath(e.target.value)}
                        placeholder="C:/Users/o/.vscode/react"
                    />
                    <button
                        className="px-3 py-2 text-sm text-white bg-gray-700 rounded"
                        onClick={() => scan(basePath)}
                    >
                        Scan
                    </button>
                </div>

                <div className="mb-4 border rounded divide-y max-h-64 overflow-auto">
                    {projects.length === 0 && (
                        <div className="p-3 text-sm text-gray-400">
                            No laravel project found in this folder
                        </div>
                    )}
                    {projects.map((p) => (
                        <button
                            key={p.root_path}
                            onClick={() => setSelected(p.root_path)}
                            className={`block w-full px-3 py-2 text-left text-sm ${
                                selected === p.root_path
                                    ? "bg-blue-50 text-blue-700"
                                    : "hover:bg-gray-50"
                            }`}
                        >
                            <div className="font-semibold">{p.name}</div>
                            <div className="text-xs text-gray-500 break-all">
                                {p.root_path}
                            </div>
                        </button>
                    ))}
                </div>

                {error && (
                    <div className="p-2 mb-3 text-xs text-red-600 bg-red-50 rounded">
                        {error}
                    </div>
                )}

                <button
                    disabled={!selected || busy}
                    onClick={save}
                    className={`w-full py-2 text-white rounded ${
                        selected && !busy
                            ? "bg-green-600 hover:bg-green-700"
                            : "bg-gray-300 cursor-not-allowed"
                    }`}
                >
                    {busy ? "Saving..." : "Confirm this Target"}
                </button>
            </div>
        </div>
    );
}
