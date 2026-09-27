import "../css/app.css";
import "@/../css/0_M_UI.css";
import "./bootstrap";

import { createInertiaApp } from "@inertiajs/react";
import { resolvePageComponent } from "laravel-vite-plugin/inertia-helpers";
import { createRoot } from "react-dom/client";
import { M_DataProvider } from "./Providers/0_M_DataProvider";

const appName = import.meta.env.VITE_APP_NAME || "Laravel";

createInertiaApp({
    title: (title) => `${title} - ${appName}`,
    resolve: (name) =>
        resolvePageComponent(
            `./Pages/${name}.jsx`,
            import.meta.glob("./Pages/**/*.jsx"),
        ),
    setup({ el, App, props }) {
        // console.count("[ROOT] App Initializing");
        const root = createRoot(el);

        root.render(
            <M_DataProvider>
                <App {...props} />
            </M_DataProvider>,
        );
    },
    progress: {
        color: "#4B5563",
    },
});
