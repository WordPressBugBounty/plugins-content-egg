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
 *       httpMethod="POST"         // optional, default "POST"
 *   />
 *
 * Defaults to POST so block attributes travel in the request body instead of
 * the query string. This avoids host firewalls / mod_security rules that flag
 * ordinary article copy in GET URLs (e.g. a literal "~"), and sidesteps the
 * ~8 KB URL-length ceiling for large blocks. The core block-renderer endpoint
 * accepts both GET and POST.
 */

import { useState, useEffect, useRef } from "@wordpress/element";
import ServerSideRender from "@wordpress/server-side-render";

export default function DebouncedServerSideRender({ attributes, debounceMs = 300, httpMethod = "POST", ...rest }) {
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

    return <ServerSideRender attributes={debouncedAttrs} httpMethod={httpMethod} {...rest} />;
}
