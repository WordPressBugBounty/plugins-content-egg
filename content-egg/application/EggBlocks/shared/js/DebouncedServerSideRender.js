/**
 * DebouncedServerSideRender
 *
 * Drop-in wrapper around @wordpress/server-side-render that debounces
 * attribute changes so the REST render call isn't fired on every keystroke.
 *
 * Usage (identical API to ServerSideRender):
 *
 *   import DebouncedServerSideRender from "../../../shared/js/DebouncedServerSideRender";
 *
 *   <DebouncedServerSideRender
 *       block="eggb/pros-cons"
 *       attributes={attributes}
 *       debounceMs={300}          // optional, default 300
 *   />
 */

import { useState, useEffect, useRef } from "@wordpress/element";
import ServerSideRender from "@wordpress/server-side-render";

export default function DebouncedServerSideRender({ attributes, debounceMs = 300, ...rest }) {
    const [debouncedAttrs, setDebouncedAttrs] = useState(attributes);
    const timer = useRef(null);

    useEffect(() => {
        if (timer.current) {
            clearTimeout(timer.current);
        }
        timer.current = setTimeout(() => {
            setDebouncedAttrs(attributes);
        }, debounceMs);

        return () => {
            if (timer.current) {
                clearTimeout(timer.current);
            }
        };
    }, [attributes, debounceMs]);

    return <ServerSideRender attributes={debouncedAttrs} {...rest} />;
}
