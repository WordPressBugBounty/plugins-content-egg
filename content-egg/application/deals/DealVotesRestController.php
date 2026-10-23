<?php

namespace ContentEgg\application\deals;

defined('\ABSPATH') || exit;

/**
 * DealVotesRestController class file
 *
 * POST /wp-json/cegg/v1/deals/{id}/vote
 *
 * No nonce: anonymous deal pages are served from page caches, and a cached
 * nonce is a dead one. For a logged-in visitor the page sends X-WP-Nonce, which
 * is what lets WordPress see them as logged in here at all.
 *
 * @author keywordrush.com <support@keywordrush.com>
 * @link https://www.keywordrush.com
 * @copyright Copyright &copy; 2026 keywordrush.com
 */
class DealVotesRestController
{
    const NAMESPACE = 'cegg/v1';

    private static $instance = null;

    public static function getInstance(): self
    {
        if (self::$instance === null)
            self::$instance = new self;

        return self::$instance;
    }

    public function init(): void
    {
        \add_action('rest_api_init', array($this, 'registerRoutes'));
    }

    public function registerRoutes(): void
    {
        \register_rest_route(self::NAMESPACE, '/deals/(?P<id>\d+)/vote', array(
            'methods' => \WP_REST_Server::CREATABLE,
            'callback' => array($this, 'vote'),
            'permission_callback' => '__return_true',
            'args' => array(
                'id' => array('type' => 'integer', 'required' => true),
                'dir' => array('type' => 'string', 'required' => true, 'enum' => array('up', 'down')),
            ),
        ));
    }

    public function vote(\WP_REST_Request $request)
    {
        $id = (int) $request['id'];
        $dir = (string) $request['dir'];

        if (!DealsSettings::votingEnabled())
            return new \WP_Error('cegg_votes_off', __('Voting is disabled.', 'content-egg-tpl'), array('status' => 403));

        $post = \get_post($id);
        if (!$post || $post->post_type !== DealPostType::POST_TYPE || $post->post_status !== 'publish' || $post->post_password !== '')
            return new \WP_Error('cegg_no_deal', __('Deal not found.', 'content-egg-tpl'), array('status' => 404));

        if (\get_post_meta($id, DealPostType::META_STATUS, true) === DealStatus::EXPIRED)
            return new \WP_Error('cegg_deal_closed', __('Voting is closed on expired deals.', 'content-egg-tpl'), array('status' => 409));

        $user_id = (int) \get_current_user_id();

        if (DealsSettings::votingLoggedInOnly() && !$user_id)
            return new \WP_Error('cegg_login_required', __('Log in to vote.', 'content-egg-tpl'), array('status' => 401));

        if (DealVotes::hasVoted($id) || ($user_id && DealVotes::userHasVoted($user_id, $id)))
            return new \WP_Error('cegg_already_voted', __('You have already voted on this deal.', 'content-egg-tpl'), array('status' => 409));

        $ip = DealVotes::clientIp();
        if (DealVotes::rateLimited($ip))
            return new \WP_Error('cegg_vote_limit', __('Too many votes. Try again later.', 'content-egg-tpl'), array('status' => 429));

        // The cookie is the browser's memory, not a guard: clearing it must not buy another vote.
        if (!DealVotes::claimVoter($id, $ip))
        {
            DealVotes::markVoted($id, $dir);
            return new \WP_Error('cegg_already_voted', __('You have already voted on this deal.', 'content-egg-tpl'), array('status' => 409));
        }

        $counts = DealVotes::record($id, $dir);
        DealVotes::markVoted($id, $dir);

        if ($user_id)
            DealVotes::markUserVoted($user_id, $id);

        return \rest_ensure_response($counts + array('voted' => $dir));
    }
}
