import { useEffect, useState } from "@wordpress/element";

function readSnapshot() {
    const snapshot = window.ceggEditorProducts;
    if (!snapshot || typeof snapshot !== "object") {
        return {
            postId: 0,
            byModule: {},
            all: [],
            updatedAt: 0,
        };
    }

    return {
        postId: Number(snapshot.postId) || 0,
        byModule: snapshot.byModule && typeof snapshot.byModule === "object" ? snapshot.byModule : {},
        all: Array.isArray(snapshot.all) ? snapshot.all : [],
        updatedAt: Number(snapshot.updatedAt) || 0,
    };
}

export default function useCeggEditorProducts() {
    const [snapshot, setSnapshot] = useState(readSnapshot);

    useEffect(() => {
        const handleUpdate = (event) => {
            if (event?.detail && typeof event.detail === "object") {
                setSnapshot(readSnapshot());
                return;
            }

            setSnapshot(readSnapshot());
        };

        window.addEventListener("ceggEditorProductsUpdated", handleUpdate);
        return () => window.removeEventListener("ceggEditorProductsUpdated", handleUpdate);
    }, []);

    return snapshot;
}
