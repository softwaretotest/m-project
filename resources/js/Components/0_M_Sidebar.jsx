import { useRef, useState } from "react";

import { use_M_Store } from "@/Stores/0_M_Store";

import {
    M_value_Service,
    make_M_value_by_selected_F_S,
} from "@/Services/0_M_value_Service";

import { useScrollIntoView } from "@/hooks/useScrollIntoView";

export default function Sidebar() {
    const M_value = use_M_Store((state) => state.M_value);

    const activeTab = use_M_Store.getState().activeTab;
    const activeSubTab = use_M_Store.getState().activeSubTab;
    const is_ENTITIES = activeTab === "entities" && activeSubTab === "entities";

    const selected_F_S = use_M_Store((state) => state.selected_F_S);
    const activeField = use_M_Store((state) => state.activeField);
    const setActiveField = use_M_Store.getState().setActiveField;

    const set_error = use_M_Store.getState().set_error;
    const set_Error_FIELDNAME = use_M_Store.getState().set_Error_FIELDNAME;

    const scrollRefs = useRef({});
    useScrollIntoView(activeField, scrollRefs);

    /**
     * Ref for drag item and state for drop blink-reding effect
     */
    const dragged_Index = useRef(null);
    const [droppedIndex, setDroppedIndex] = useState(null);

    /**
     * function on Start dragging
     * @param {*} index
     */
    const handleDragStart = (index) => {
        dragged_Index.current = index;
    };

    /**
     * Clear drag-over highlight when drag operation ends
     * @param {*} event
     */
    const handleDragEnd = (event) => {
        // ล้างคลาส drag-over ออกจากทุกปุ่มเพื่อความชัวร์ว่าไม่ค้าง
        document.querySelectorAll(".field-nav-link").forEach((btn) => {
            btn.classList.remove("drag-over");
        });
    };

    /**
     * * prevent default of dragover
     * * to except drop
     * @param {*} event
     */
    const handleDragOver = (event) => {
        event.preventDefault();
    };

    /**
     * Highlight target button when dragging over
     * @param {*} event
     */
    const handleDragEnter = (event) => {
        event.currentTarget.classList.add("drag-over");
    };

    /**
     * Remove highlight when dragging leaves button
     * @param {*} event
     */
    const handleDragLeave = (event) => {
        event.currentTarget.classList.remove("drag-over");
    };

    /**
     * * get firts Item Name
     * * check if drop on the first item, then set_error
     * * Re-order selected_F_S on Drop
     * * save new sorted Entities to backend , after drop
     * * dropped item blink-reds 3000ms after drop
     * * auto scroll to dropped item and set it to active field
     * @param {*} target_Index
     * @returns
     */
    async function handleDrop(event, target_Index) {
        const source_Index = dragged_Index.current;
        if (source_Index === target_Index) return;

        const selected_F_S_KEYS = Object.keys(selected_F_S);

        // get firts Item Name
        const target_Name = selected_F_S_KEYS[target_Index]
            ? selected_F_S_KEYS[target_Index]
                  .toUpperCase()
                  .replaceAll("T::", "")
            : "";

        // check if drop on the first item (USERS), then set_error
        if (target_Index === 0 && target_Name === "USERS") {
            set_error("User must be at the top", { clear_in_ms: 4000 });
            target_Index = 1;
        }

        // re-order sidebar buttons , USERS always at the top
        const [movedItem] = selected_F_S_KEYS.splice(source_Index, 1);
        selected_F_S_KEYS.splice(target_Index, 0, movedItem);
        use_M_Store.getState().reorder_selected_F_S(selected_F_S_KEYS);
        // reset drag-index
        dragged_Index.current = null;

        // save new sorted Entities to backend , after drop
        const new_M_value = make_M_value_by_selected_F_S();
        await M_value_Service.update(new_M_value);

        // dropped item blink-reds 3000ms after drop
        setDroppedIndex(target_Index);
        setTimeout(() => {
            setDroppedIndex((prev) => (prev === target_Index ? null : prev));
        }, 3000);

        // auto scroll to dropped item and set it to active field
        setActiveField(selected_F_S_KEYS[target_Index].toLocaleLowerCase());
    }

    let M_value_for_Loop = {};
    if (is_ENTITIES) {
        M_value_for_Loop = selected_F_S;
    } else {
        M_value_for_Loop = M_value;
    }

    return (
        <nav className="field-sidebar">
            {Object.keys(M_value_for_Loop).map((M_value_KEY, index) => (
                <button
                    key={M_value_KEY.toLowerCase()}
                    ref={(DOM_Node) =>
                        (scrollRefs.current[M_value_KEY.toLowerCase()] =
                            DOM_Node)
                    }
                    // --------  Drag and Drop acition ----------
                    className={`field-nav-link 
                        ${activeField === M_value_KEY.toLowerCase() ? "active" : ""} 
                        ${droppedIndex === index ? "dropped-blink-red" : ""}`}
                    draggable={
                        activeTab === "entities" && activeSubTab === "entities"
                    }
                    onDragStart={() => handleDragStart(index)}
                    onDragEnd={handleDragEnd}
                    onDragOver={handleDragOver}
                    onDragEnter={handleDragEnter}
                    onDragLeave={handleDragLeave}
                    onDrop={(event) => handleDrop(event, index)}
                    // ------------------------------------

                    onClick={() => {
                        // M_value_KEY = null , when field deleted
                        if (M_value_KEY)
                            setActiveField(M_value_KEY.toLowerCase());
                    }}
                >
                    {/* if Class t (DB_Tablename) remove T:: */}
                    {M_value_KEY.toUpperCase().replaceAll("T::", "")}
                </button>
            ))}
        </nav>
    );
}
