!(function (e) {
  "function" == typeof define && define.amd ? define(e) : e();
})(function () {
  "use strict";
  /*!
   * Content Egg Checkout Block
   * @author keywordrush.com <support@keywordrush.com>
   * @link https://www.keywordrush.com
   * @copyright Copyright &copy; 2026 keywordrush.com
   */ document.addEventListener("DOMContentLoaded", function () {
    if (!window.wc || !wc.blocksCheckout) return;
    const { registerCheckoutFilters: e } = wc.blocksCheckout,
      o = !!window.ceggAmazonCheckout && !!ceggAmazonCheckout.hasAmazonItems;
    e("content-egg-amazon", {
      proceedToCheckoutButtonLink: (e, c, n) =>
        o &&
        window.ceggAmazonCheckout &&
        ceggAmazonCheckout.enabled &&
        ceggAmazonCheckout.checkoutUrl
          ? ceggAmazonCheckout.checkoutUrl
          : e,
      proceedToCheckoutButtonLabel: (e, c, n) =>
        (o &&
          window.ceggAmazonCheckout &&
          ceggAmazonCheckout.enabled &&
          ceggAmazonCheckout.buttonLabel) ||
        e,
    });
  });
});
