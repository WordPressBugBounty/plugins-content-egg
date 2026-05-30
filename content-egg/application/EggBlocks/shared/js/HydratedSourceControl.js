/**
 * HydratedSourceControl
 *
 * Reusable inspector control that renders either a source-key dropdown
 * (auto/hydration mode) or a literal text input (manual mode), based on the
 * `useSource` flag.
 *
 * Used by EggBlocks to expose attributes that can be hydrated from a TMN
 * profile via the `eggb/resolve_*_source` filter chain.
 *
 * Props:
 *   label              string  — control label
 *   help               string  — help text
 *   useSource          bool    — true → render source dropdown, false → text input
 *   sourceValue        string  — current value of the *_source attribute
 *   literalValue       string  — current value of the literal attribute
 *   options            array   — [{ value, label }] options for the source dropdown
 *                                (the empty "— None —" option is prepended automatically)
 *   onSourceChange     fn      — invoked when the source dropdown changes
 *   onLiteralChange    fn      — invoked when the text input changes
 *   noOptionsNotice    string  — optional message when the linked profile has no
 *                                non-empty values for this control's purpose
 */

import { SelectControl, TextControl, Notice } from "@wordpress/components";
import { __ } from "@wordpress/i18n";

const NONE_OPTION = { value: "", label: __("— None —", "content-egg") };

export default function HydratedSourceControl({
    label,
    help,
    useSource,
    sourceValue,
    literalValue,
    options = [],
    onSourceChange,
    onLiteralChange,
    noOptionsNotice,
}) {
    if (useSource) {
        const hasOptions = options.length > 0;
        const allOptions = [NONE_OPTION, ...options];

        return (
            <>
                <SelectControl
                    label={label}
                    help={help}
                    value={sourceValue || ""}
                    options={allOptions}
                    onChange={onSourceChange}
                    __nextHasNoMarginBottom
                />
                {!hasOptions && (
                    <Notice status="warning" isDismissible={false}>
                        {noOptionsNotice ||
                            __(
                                "The linked profile has no values for this field.",
                                "content-egg"
                            )}
                    </Notice>
                )}
            </>
        );
    }

    return (
        <TextControl
            label={label}
            help={help}
            value={literalValue || ""}
            onChange={onLiteralChange}
            __nextHasNoMarginBottom
        />
    );
}
