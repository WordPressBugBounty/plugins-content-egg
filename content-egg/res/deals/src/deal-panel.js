import { registerPlugin } from '@wordpress/plugins';
import { PluginDocumentSettingPanel } from '@wordpress/editor';
import { useSelect } from '@wordpress/data';
import { useEffect, useState } from '@wordpress/element';
import apiFetch from '@wordpress/api-fetch';
import { Button, Notice, SelectControl, Spinner, TextControl } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

const itemLabel = (i) =>
    [i.merchant, (i.title || '').slice(0, 50), i.price ? `${i.price} ${i.currency}` : ''].filter(Boolean).join(' — ');

const DealPanel = () => {
    const { postId, postType } = useSelect(
        (select) => ({
            postId: select('core/editor').getCurrentPostId(),
            postType: select('core/editor').getCurrentPostType(),
        }),
        []
    );
    const [state, setState] = useState(null);
    const [busy, setBusy] = useState(false);
    const [error, setError] = useState('');
    const [ends, setEnds] = useState('');
    const [couponCode, setCouponCode] = useState('');
    const [couponNote, setCouponNote] = useState('');

    const path = `/cegg/v1/deals/${postId}/state`;
    const apply = (s) => {
        setState(s);
        setEnds(s.ends || '');
        setCouponCode(s.coupon_code || '');
        setCouponNote(s.coupon_note || '');
    };
    const load = () => apiFetch({ path }).then(apply).catch((e) => setError(e.message));
    const send = (data) => {
        setBusy(true);
        setError('');
        return apiFetch({ path, method: 'POST', data })
            .then(apply)
            .catch((e) => setError(e.message))
            .finally(() => setBusy(false));
    };

    useEffect(() => {
        if (postType === 'cegg_deal' && postId) {
            load();
        }
    }, [postId, postType]);

    if (postType !== 'cegg_deal') {
        return null;
    }

    const expired = state && state.status === 'expired';
    const pinValue = state && state.pin ? `${state.pin.module_id}|${state.pin.unique_id}` : '';
    const endsDirty = state && ends !== (state.ends || '');
    const couponDirty = state && (couponCode !== (state.coupon_code || '') || couponNote !== (state.coupon_note || ''));

    const status = !state || !state.status
        ? { label: __('No product yet', 'content-egg'), color: '#8a6d00', bg: '#fcf3d6' }
        : expired
        ? { label: state.reason_label || __('Expired', 'content-egg'), color: '#50575e', bg: '#f0f0f1' }
        : { label: __('Active', 'content-egg'), color: '#00783c', bg: '#e6f4ea' };

    return (
        <PluginDocumentSettingPanel name="cegg-deal-panel" title={__('Deal', 'content-egg')}>
            {error && <Notice status="error" isDismissible={false}>{error}</Notice>}
            {!state && <Spinner />}
            {state && (
                <div style={{ opacity: busy ? 0.6 : 1 }}>
                    <div style={css.head}>
                        <div style={{ minWidth: 0 }}>
                            <span style={{ ...css.pill, color: status.color, background: status.bg }}>{status.label}</span>
                            {state.price_text && (
                                <div style={css.price}>
                                    {state.price_text}
                                    {state.discount > 0 && <span style={css.off}> −{state.discount}%</span>}
                                </div>
                            )}
                        </div>
                        <div style={css.score} title={__('Votes up / down', 'content-egg')}>
                            <div style={css.scoreNum}>{state.score > 0 ? `+${state.score}` : state.score}</div>
                            <div style={css.muted}>
                                ▲ {state.votes_up} · ▼ {state.votes_down}
                            </div>
                        </div>
                    </div>
                    {!state.status && (
                        <p style={css.muted}>{__('Add a product with Content Egg, save the post, then press Refresh.', 'content-egg')}</p>
                    )}

                    {state.items.length > 0 && (
                        <div style={css.section}>
                            <SelectControl
                                __nextHasNoMarginBottom
                                label={__('Main offer', 'content-egg')}
                                value={pinValue}
                                disabled={busy}
                                options={[
                                    ...(pinValue ? [] : [{ value: '', label: __('Choose…', 'content-egg') }]),
                                    ...state.items.map((i) => ({ value: `${i.module_id}|${i.unique_id}`, label: itemLabel(i) })),
                                ]}
                                onChange={(v) => {
                                    const [module_id, ...rest] = v.split('|');
                                    if (module_id) {
                                        send({ action: 'pin', module_id, unique_id: rest.join('|') });
                                    }
                                }}
                            />
                        </div>
                    )}

                    <div style={css.section}>
                        <TextControl
                            __nextHasNoMarginBottom
                            type="datetime-local"
                            label={__('Deal ends', 'content-egg')}
                            value={ends}
                            disabled={busy}
                            onChange={setEnds}
                            help={__('Optional. The deal expires at this time.', 'content-egg')}
                        />
                        {(endsDirty || state.ends) && (
                            <div style={css.row}>
                                {endsDirty && (
                                    <Button size="compact" variant="primary" disabled={busy} onClick={() => send({ action: 'ends', value: ends })}>
                                        {__('Save end date', 'content-egg')}
                                    </Button>
                                )}
                                {state.ends && !endsDirty && (
                                    <Button size="compact" variant="tertiary" disabled={busy} onClick={() => send({ action: 'ends', value: '' })}>
                                        {__('Clear end date', 'content-egg')}
                                    </Button>
                                )}
                            </div>
                        )}
                    </div>

                    <div style={css.section}>
                        <TextControl
                            __nextHasNoMarginBottom
                            label={__('Coupon code', 'content-egg')}
                            value={couponCode}
                            disabled={busy}
                            onChange={setCouponCode}
                            help={__('Optional. Shown next to the price with a Copy button.', 'content-egg')}
                        />
                        <div style={{ height: 12 }} />
                        <TextControl
                            __nextHasNoMarginBottom
                            label={__('Coupon note', 'content-egg')}
                            value={couponNote}
                            disabled={busy}
                            onChange={setCouponNote}
                            placeholder={__('e.g. Extra 20% off at checkout', 'content-egg')}
                        />
                        {(couponDirty || state.coupon_code) && (
                            <div style={css.row}>
                                {couponDirty && (
                                    <Button
                                        size="compact"
                                        variant="primary"
                                        disabled={busy}
                                        onClick={() => send({ action: 'coupon', code: couponCode, note: couponNote })}
                                    >
                                        {__('Save coupon', 'content-egg')}
                                    </Button>
                                )}
                                {state.coupon_code && !couponDirty && (
                                    <Button size="compact" variant="tertiary" isDestructive disabled={busy} onClick={() => send({ action: 'coupon', code: '', note: '' })}>
                                        {__('Remove coupon', 'content-egg')}
                                    </Button>
                                )}
                            </div>
                        )}
                    </div>

                    <div style={{ ...css.section, ...css.row, justifyContent: 'space-between', marginTop: 16 }}>
                        {state.status ? (
                            <Button size="compact" variant="secondary" isDestructive={!expired} disabled={busy} onClick={() => send({ action: expired ? 'activate' : 'expire' })}>
                                {expired ? __('Mark active again', 'content-egg') : __('Mark expired', 'content-egg')}
                            </Button>
                        ) : (
                            <span />
                        )}
                        <Button size="compact" variant="tertiary" icon="update" disabled={busy} onClick={load} label={__('Refresh', 'content-egg')} showTooltip>
                            {__('Refresh', 'content-egg')}
                        </Button>
                    </div>
                </div>
            )}
        </PluginDocumentSettingPanel>
    );
};

const css = {
    head: { display: 'flex', justifyContent: 'space-between', alignItems: 'flex-start', gap: 12 },
    pill: { display: 'inline-block', padding: '2px 8px', borderRadius: 999, fontSize: 11, fontWeight: 600, lineHeight: '18px' },
    price: { marginTop: 6, fontSize: 18, fontWeight: 600, lineHeight: 1.3 },
    off: { color: '#00783c', fontSize: 13, fontWeight: 600 },
    score: { textAlign: 'right', flex: 'none' },
    scoreNum: { fontSize: 18, fontWeight: 600, lineHeight: 1.3 },
    muted: { color: '#757575', fontSize: 12, margin: 0 },
    section: { borderTop: '1px solid #e0e0e0', marginTop: 16, paddingTop: 16 },
    row: { display: 'flex', gap: 8, flexWrap: 'wrap', alignItems: 'center', marginTop: 8 },
};

registerPlugin('cegg-deal-panel', { render: DealPanel });
