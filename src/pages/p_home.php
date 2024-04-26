<?php
// pages/p_home.php -- HotCRP home page
// Copyright (c) 2006-2024 Eddie Kohler; see LICENSE.

class Home_Page {

    /** @var Conf
     * @readonly */
    public $conf;
    /** @var Contact
     * @readonly */
    public $user;
    /** @var int */
    private $_nh2 = 0;
    /** @var bool */
    private $_has_sidebar = false;
    private $_in_reviews;
    /** @var ?list<ReviewField> */
    private $_rfs;
    /** @var int */
    private $_r_num_submitted = 0;
    /** @var int */
    private $_r_num_needs_submit = 0;
    /** @var list<int> */
    private $_r_unsubmitted_rounds;
    /** @var list<int|float> */
    private $_rf_means;
    private $_tokens_done;

    function __construct(Contact $user) {
        $this->conf = $user->conf;
        $this->user = $user;
    }

    static function disabled_request(Contact $user, Qrequest $qreq) {
        if (!$user->is_empty() && $user->is_disabled()) {
            $user->conf->warning_msg($user->conf->_i("account_disabled"));
            $qreq->print_header("Account disabled", "home", ["action_bar" => ""]);
            $qreq->print_footer();
            exit;
        }
    }

    static function reviewtokenreport_request(Contact $user, Qrequest $qreq) {
        if (!$user->is_empty() && $qreq->reviewtokenreport) {
            $ml = [];
            if (!$user->review_tokens()) {
                $ml[] = MessageItem::success("<0>Review tokens cleared");
            } else {
                $result = $user->conf->qe("select reviewToken, paperId from PaperReview where reviewToken?a order by paperId", $user->review_tokens());
                while (($row = $result->fetch_row())) {
                    $ml[] = MessageItem::success("<5>Review token ‘" . htmlspecialchars(encode_token((int) $row[0])) . "’ lets you review " . Ht::link("{$user->conf->snouns[0]} #{$row[1]}", $user->conf->hoturl("paper", "p={$row[1]}")));
                }
            }
            $user->conf->feedback_msg($ml);
        }
    }

    static function profile_redirect_request(Contact $user, Qrequest $qreq) {
        if (!$user->is_empty() && $qreq->postlogin) {
            LoginHelper::check_postlogin($user, $qreq);
        }
        if ($user->has_account_here()
            && $qreq->csession("freshlogin") === true) {
            if (self::need_profile_redirect($user)) {
                $qreq->set_csession("freshlogin", "redirect");
                $user->conf->redirect_hoturl("profile", "redirect=1");
            } else {
                $qreq->unset_csession("freshlogin");
            }
        }
    }

    static function need_profile_redirect(Contact $user) {
        if (!$user->firstName && !$user->lastName) {
            return true;
        } else if ($user->conf->opt("noProfileRedirect")) {
            return false;
        } else {
            return !$user->affiliation
                || ($user->is_pc_member()
                    && !$user->has_review()
                    && (!$user->collaborators()
                        || ($user->conf->has_topics()
                            && !$user->topic_interest_map())));
        }
    }

    function print_head(Contact $user, Qrequest $qreq, $gx) {
        $qreq->print_header("Home", "home");
        if ($qreq->signedout && $user->is_empty()) {
            $user->conf->success_msg("<0>You have been signed out of the site");
        }
        $gx->push_print_cleanup("__footer");
        echo '<noscript><div class="msg msg-error"><strong>This site requires JavaScript.</strong> Your browser does not support JavaScript.<br><a href="https://github.com/kohler/hotcrp/">Report bad compatibility problems</a></div></noscript>', "\n";
        if ($user->privChair) {
            echo '<div id="p-clock-drift" class="homegrp hidden"></div>';
        }
    }

    function print_content(Contact $user, Qrequest $qreq, $gx) {
        echo '<main class="home-content">';
        ob_start();
        $gx->print_members("home/sidebar");
        if (($t = ob_get_clean()) !== "") {
            echo '<nav class="home-sidebar">', $t, '</nav>';
            $this->_has_sidebar = true;
        }
        echo '<div class="home-main">';
        $gx->print_members("home/main");
        echo "</div></main>\n";
    }

    private function print_h2_home($x) {
        ++$this->_nh2;
        return "<h2 class=\"home\">" . $x . "</h2>";
    }

    static function print_admin_sidebar(Contact $user, Qrequest $qreq, $gx) {
        echo '<div class="homegrp"><h2 class="home">Administration</h2><ul>';
        $gx->print_members("home/sidebar/admin");
        echo '</ul></div>';
    }
    static function print_admin_settings(Contact $user) {
        echo '<li>', Ht::link("Settings", $user->conf->hoturl("settings")), '</li>';
    }
    static function print_admin_users(Contact $user) {
        $t = $user->privChair ? "all" : "re";
        echo '<li>', Ht::link("Users", $user->conf->hoturl("users", ["t" => $t])), '</li>';
    }
    static function print_admin_assignments(Contact $user) {
        echo '<li>', Ht::link("Assignments", $user->conf->hoturl("autoassign")), '</li>';
    }
    static function print_admin_mail(Contact $user) {
        echo '<li>', Ht::link("Mail", $user->conf->hoturl("mail")), '</li>';
    }
    static function print_admin_log(Contact $user) {
        echo '<li>', Ht::link("Action log", $user->conf->hoturl("log")), '</li>';
    }

    static function print_info_sidebar(Contact $user, Qrequest $qreq, $gx) {
        ob_start();
        $gx->print_members("home/sidebar/info");
        if (($t = ob_get_clean())) {
            echo '<div class="homegrp"><h2 class="home">',
                $user->conf->_c("home", "Conference information"),
                '</h2><ul>', $t, '</ul></div>';
        }
    }
    static function print_info_deadline(Contact $user) {
        if ($user->has_reportable_deadline()) {
            echo '<li>', Ht::link("Deadlines", $user->conf->hoturl("deadlines")), '</li>';
        }
    }
    static function print_info_pc(Contact $user) {
        if ($user->can_view_pc()) {
            echo '<li>', Ht::link("Program committee", $user->conf->hoturl("users", "t=pc")), '</li>';
        }
    }
    static function print_info_site(Contact $user) {
        if (($site = $user->conf->opt("conferenceSite"))
            && $site !== $user->conf->opt("paperSite")) {
            echo '<li>', Ht::link("Conference site", $site), '</li>';
        }
    }
    static function print_info_accepted(Contact $user) {
        assert($user->conf->time_all_author_view_decision());
        if ($user->conf->time_all_author_view_decision()) {
            list($n, $nyes) = $user->conf->count_submitted_accepted();
            echo '<li>', $user->conf->_("{} papers accepted out of {} submitted.", $nyes, $n), '</li>';
        }
    }

    function print_message() {
        if (($t = $this->conf->_i("home"))) {
            echo '<div class="msg ',
                $this->_has_sidebar ? 'avoid-home-sidebar' : 'maxw-auto',
                ' mb-5">', $t, '</div>';
        }
    }

    function print_welcome() {
        echo '<div class="homegrp">Welcome to the ', htmlspecialchars($this->conf->full_name()), " submissions site.";
        if (($site = $this->conf->opt("conferenceSite"))
            && $site !== $this->conf->opt("paperSite"))
            echo " For general conference information, see ", Ht::link(htmlspecialchars($site), htmlspecialchars($site)), ".";
        echo '</div>';
    }

    function print_signin(Contact $user, Qrequest $qreq, $gx) {
        if (!$user->has_email() || $qreq->signin) {
            Signin_Page::print_signin_form($user, $qreq, $gx);
        }
    }

    function print_search(Contact $user, Qrequest $qreq, $gx) {
        if (!$user->privChair
            && ($user->isPC
                ? !$this->conf->setting("pc_seeall") && !$this->conf->has_any_submitted()
                : !$user->is_reviewer())) {
            return;
        }

        $limits = PaperSearch::viewable_limits($user);
        echo '<div class="homegrp d-table" id="homelist">',
            $this->print_h2_home('<a class="q" href="' . $this->conf->hoturl("search") . '" id="homesearch-label">Search</a>'),
            Ht::form($this->conf->hoturl("search"), ["method" => "get", "class" => "form-basic-search"]),
            Ht::entry("q", (string) $qreq->q, [
                "id" => "homeq", "size" => 32,
                "title" => "Enter paper numbers or search terms",
                "class" => "papersearch need-suggest flex-grow-1 mb-1",
                "placeholder" => "(All)",
                "aria-labelledby" => "homesearch-label",
                "spellcheck" => false, "autocomplete" => "off"
            ]), '<div class="form-basic-search-in"> in ',
            PaperSearch::limit_selector($this->conf, $limits, PaperSearch::default_limit($user, $limits)),
            Ht::submit("Search"),
            "</div></form>";

        if ($user->isPC) {
            $hs = [];
            foreach ($user->conf->named_searches() as $sj) {
                if (($sj->display ?? null) === "highlight")
                    $hs[] = '<li>⭐️ ' . Ht::link("ss:" . htmlspecialchars($sj->name), $this->conf->hoturl("search", ["q" => "ss:{$sj->name}"])) . '</li>';
            }
            if (!empty($hs)) {
                echo '<div class="mt-1 font-weight-semibold"><ul class="inline">',
                    join("", $hs), '</ul></div>';
            }
        }

        echo '</div>';
    }

    /** @return list<Score_ReviewField> */
    private function default_review_fields() {
        $this->_rfs = $this->_rfs ?? $this->conf->review_form()->highlighted_main_scores();
        return $this->_rfs;
    }

    /** @param string $setting
     * @return ?string */
    private function setting_time_span($setting) {
        $t = $this->conf->setting($setting) ?? 0;
        return $t > 0 ? $this->conf->unparse_time_with_local_span($t) : null;
    }

    function print_reviews(Contact $user, Qrequest $qreq, $gx) {
        $conf = $user->conf;
        if (!$user->privChair
            && !($user->is_reviewer() && $conf->has_any_submitted())) {
            return;
        }

        // which review fields to show?

        // Information about my reviews
        $where = [];
        if ($user->contactId) {
            $where[] = "PaperReview.contactId=" . $user->contactId;
        }
        if (($tokens = $user->review_tokens())) {
            $where[] = "reviewToken in (" . join(",", $tokens) . ")";
        }
        if (!empty($where)) {
            $rfs = $this->default_review_fields();
            $q = "select reviewType, reviewSubmitted, reviewNeedsSubmit, timeApprovalRequested, reviewRound";
            $missing_rounds = $scores = [];
            foreach ($rfs as $rf) {
                $q .= ", " . $rf->main_storage;
                $scores[] = [];
            }
            $result = $user->conf->qe("{$q} from PaperReview join Paper using (paperId) where (" . join(" or ", $where) . ") and (reviewSubmitted is not null or timeSubmitted>0)");
            while (($row = $result->fetch_row())) {
                if ($row[1] || $row[3] < 0) {
                    $this->_r_num_submitted += 1;
                    $this->_r_num_needs_submit += 1;
                    for ($i = 0; $i !== count($rfs); ++$i) {
                        if ($row[5 + $i] !== null)
                            $scores[$i][] = (int) $row[5 + $i];
                    }
                } else if ($row[2]) {
                    $this->_r_num_needs_submit += 1;
                    $missing_rounds[(int) $row[4]] = true;
                }
            }
            Dbl::free($result);
            $this->_r_unsubmitted_rounds = array_keys($missing_rounds);
            $this->_rf_means = [];
            foreach ($scores as $sarr) {
                $this->_rf_means[] = ScoreInfo::mean_of($sarr, true);
            }
        }
        $has_rinfo = $this->_r_num_needs_submit > 0;

        // Information about PC reviews
        $npc = $sumpc_submit = $npc_submit = 0;
        $pc_rf_means = [];
        if ($user->isPC || $user->privChair) {
            $rfs = $this->default_review_fields();
            $q = "select count(reviewId) num_submitted";
            $scores = [];
            foreach ($rfs as $rf) {
                $q .= ", group_concat(coalesce({$rf->main_storage},'')) {$rf->short_id}Scores";
                $scores[] = [];
            }
            $result = Dbl::qe_raw("{$q} from ContactInfo left join PaperReview on (PaperReview.contactId=ContactInfo.contactId and PaperReview.reviewSubmitted is not null)
                where roles!=0 and (roles&" . Contact::ROLE_PC . ")!=0 group by ContactInfo.contactId");
            while (($row = $result->fetch_row())) {
                ++$npc;
                if ($row[0]) {
                    $npc_submit += 1;
                    $sumpc_submit += (int) $row[0];
                    for ($i = 0; $i !== count($rfs); ++$i) {
                        $scores[$i][] = ScoreInfo::mean_of($row[1 + $i], true);
                    }
                }
            }
            Dbl::free($result);
            foreach ($scores as $sarr) {
                $pc_rf_means[] = ScoreInfo::mean_of($sarr, true);
            }
        }

        echo '<div class="homegrp" id="homerev">';

        // Overview
        echo $this->print_h2_home("Reviews");
        if ($has_rinfo) {
            $score_texts = [];
            foreach ($this->default_review_fields() as $i => $rf) {
                if ($this->_rf_means[$i] !== null) {
                    $score_texts[] = $conf->_("average {0} score {1}", $rf->name_html, $rf->unparse_computed($this->_rf_means[$i], "%.2f"), $this->_r_num_submitted);
                }
            }
            echo $conf->_("You have submitted {n} of <a href=\"{url}\">{na} reviews</a> with {scores:list}.",
                new FmtArg("n", $this->_r_num_submitted), new FmtArg("na", $this->_r_num_needs_submit),
                new FmtArg("url", $conf->hoturl_raw("search", "q=&t=r"), 0),
                new FmtArg("scores", $score_texts)),
                "<br>\n";
        }
        if (($user->isPC || $user->privChair) && $npc) {
            $score_texts = [];
            foreach ($this->default_review_fields() as $i => $rf) {
                if ($pc_rf_means[$i] !== null) {
                    $score_texts[] = $conf->_("average {0} score {1}", $rf->name_html, $rf->unparse_computed($pc_rf_means[$i], "%.2f"), null);
                }
            }
            echo $conf->_("The average PC member has submitted {n:.1f} reviews with {scores:list}.",
                new FmtArg("n", $sumpc_submit / $npc), new FmtArg("scores", $score_texts));
            if ($user->isPC || $user->privChair) {
                echo "&nbsp; <small class=\"nw\">(<a href=\"", $conf->hoturl("users", "t=pc"), "\">details</a><span class=\"barsep\">·</span><a href=\"", $conf->hoturl("graph", "group=procrastination"), "\">graphs</a>)</small>";
            }
            echo "<br>\n";
        }
        if ($this->_r_num_submitted < $this->_r_num_needs_submit
            && !$conf->time_review_open()) {
            echo ' <em class="deadline">The site is not open for reviewing.</em><br>', "\n";
        } else if ($this->_r_num_submitted < $this->_r_num_needs_submit) {
            sort($this->_r_unsubmitted_rounds, SORT_NUMERIC);
            foreach ($this->_r_unsubmitted_rounds as $round) {
                if (($rname = $conf->round_name($round))) {
                    if (strlen($rname) == 1) {
                        $rname = "“{$rname}”";
                    }
                    $rname .= " ";
                }
                if ($conf->time_review($round, $user->isPC, false)) {
                    $dn = $conf->review_deadline_name($round, $user->isPC, false);
                    if ($conf->setting($dn) <= 0) {
                        $dn = $conf->review_deadline_name($round, $user->isPC, true);
                    }
                    if (($d = $this->setting_time_span($dn))) {
                        echo ' <em class="deadline">Please submit your ', $rname, ($this->_r_num_needs_submit == 1 ? "review" : "reviews"), " by {$d}.</em><br>\n";
                    }
                } else if ($conf->time_review($round, $user->isPC, true)) {
                    $dn = $conf->review_deadline_name($round, $user->isPC, false);
                    $d = $this->setting_time_span($dn);
                    echo ' <em class="deadline"><strong class="overdue">', $rname, ($rname ? "reviews" : "Reviews"), ' are overdue.</strong> They were requested by ', $d, ".</em><br>\n";
                } else {
                    echo ' <em class="deadline"><strong class="overdue">The <a href="', $conf->hoturl("deadlines"), '">deadline</a> for submitting ', $rname, "reviews has passed.</strong></em><br>\n";
                }
            }
        } else if ($user->isPC && $user->can_review_any()) {
            $dn = $conf->review_deadline_name(null, $user->isPC, false);
            if (($d = $this->setting_time_span($dn))) {
                echo " <em class=\"deadline\">The review deadline is {$d}.</em><br>\n";
            }
        }
        if ($user->isPC && $user->can_review_any()) {
            echo '  <span class="hint">As a PC member, you may review <a href="', $conf->hoturl("search", "q=&amp;t=s"), "\">any submitted paper</a>.</span><br>\n";
        } else if ($user->privChair) {
            echo '  <span class="hint">As an administrator, you may review <a href="', $conf->hoturl("search", "q=&amp;t=s"), "\">any submitted paper</a>.</span><br>\n";
        }

        if ($has_rinfo) {
            echo '<div id="foldre" class="homesubgrp foldo">';
        }

        // Actions
        $sep = "";
        $xsep = ' <span class="barsep">·</span> ';
        if ($has_rinfo) {
            echo $sep, foldupbutton(), "<a href=\"", $conf->hoturl("search", "q=re%3Ame"), "\" title=\"Search in your reviews (more display and download options)\"><strong>Your Reviews</strong></a>";
            $sep = $xsep;
        }
        if ($user->isPC && $user->is_discussion_lead()) {
            echo $sep, '<a href="', $conf->hoturl("search", "q=lead%3Ame"), '" class="nw">Your discussion leads</a>';
            $sep = $xsep;
        }
        if ($conf->time_review_open() || $user->privChair) {
            echo $sep, '<a href="', $conf->hoturl("offline"), '">Offline reviewing</a>';
            $sep = $xsep;
        }
        if ($user->isPC && $conf->timePCReviewPreferences()) {
            echo $sep, '<a href="', $conf->hoturl("reviewprefs"), '">Review preferences</a>';
            $sep = $xsep;
        }
        if ($conf->setting("rev_tokens")) {
            echo $sep;
            $this->_in_reviews = true;
            $this->print_review_tokens($user, $qreq, $gx);
            $sep = $xsep;
        }

        if ($has_rinfo
            && $conf->setting("rev_ratings") != REV_RATINGS_NONE) {
            $badratings = PaperSearch::unusable_ratings($user);
            $qx = (count($badratings) ? " and not (PaperReview.reviewId in (" . join(",", $badratings) . "))" : "");
            $result = $conf->qe_raw("select sum((rating&" . ReviewInfo::RATING_GOODMASK . ")!=0), sum((rating&" . ReviewInfo::RATING_BADMASK . ")!=0) from PaperReview join ReviewRating using (reviewId) where PaperReview.contactId={$user->contactId} $qx");
            $row = $result->fetch_row();
            '@phan-var list $row';
            Dbl::free($result);

            $a = [];
            if ($row[0]) {
                $a[] = Ht::link(plural($row[0], "positive rating"), $conf->hoturl("search", "q=rate:good:me"));
            }
            if ($row[1]) {
                $a[] = Ht::link(plural($row[1], "negative rating"), $conf->hoturl("search", "q=rate:bad:me"));
            }
            if (!empty($a)) {
                echo '<div class="hint g">Your reviews have received ', commajoin($a), '.</div>';
            }
        }

        if ($user->has_review()) {
            $plist = new PaperList("reviewerHome", new PaperSearch($user, ["q" => "re:me"]));
            $plist->set_table_id_class(null, "pltable-reviewerhome");
            if (!$plist->is_empty()) {
                echo '<div class="fx"><hr class="g">';
                $plist->set_table_decor(PaperList::DECOR_HEADER | PaperList::DECOR_LIST);
                $plist->print_table_html();
                echo '</div>';
            }
        }

        if ($has_rinfo) {
            echo "</div>";
        }

        if ($user->is_reviewer()) {
            echo "<div class=\"homesubgrp has-fold fold20c ui-fold js-open-activity need-fold-storage\" id=\"homeactivity\" data-fold-storage=\"homeactivity\">",
                foldupbutton(20),
                "<a href=\"\" class=\"q homeactivity ui js-foldup\" data-fold-target=\"20\">Recent activity<span class=\"fx20\">:</span></a>",
                "</div>";
            Ht::stash_script("hotcrp.fold_storage()");
        }

        echo "</div>\n";
	if ($user->is_reviewer()) {
		echo "<!-- this is a hidden comment for debugging -->";
        	$this->print_vm_interface($user);
	}
    }

    // Review token printing
    function print_review_tokens(Contact $user, Qrequest $qreq, $gx) {
        if (!$this->_tokens_done
            && $user->has_email()
            && $user->conf->setting("rev_tokens")
            && (!$this->_in_reviews || $user->is_reviewer())) {
            if (!$this->_in_reviews) {
                echo '<div class="homegrp" id="homerev">',
                    $this->print_h2_home("Reviews");
            }
            $tokens = array_map("encode_token", $user->review_tokens());
            $ttexts = array_map(function ($t) use ($user) {
                return Ht::link($t, $user->conf->hoturl("paper", ["q" => "token:$t"]));
            }, $tokens);
            echo '<button type="button" class="link ui js-review-tokens" data-review-tokens="',
                join(" ", $tokens), '">Review tokens</button>',
                (empty($tokens) ? "" : " (" . join(", ", $ttexts) . ")");
            if (!$this->_in_reviews) {
                echo '</div>', "\n";
            }
            $this->_tokens_done = true;
        }
    }

    function print_review_requests(Contact $user, Qrequest $qreq, $gx) {
        $conf = $user->conf;
        if (!$user->is_requester()
            && !$user->has_review_pending_approval()
            && !$user->has_proposal_pending()) {
            return;
        }

        echo '<div class="homegrp">', $this->print_h2_home("Requested Reviews");
        if ($user->has_review_pending_approval()) {
            echo '<a href="', $conf->hoturl("paper", "m=rea&amp;q=re%3Apending-my-approval"),
                ($user->has_review_pending_approval(true) ? '" class="attention' : ''),
                '">Reviews pending approval</a> <span class="barsep">·</span> ';
        }
        if ($user->has_proposal_pending()) {
            echo '<a href="', $conf->hoturl("assign", "q=re%3Aproposal"),
                '" class="attention">Review proposals</a> <span class="barsep">·</span> ';
        }
        echo '<a href="', $conf->hoturl("mail", "monreq=1"), '">Monitor requested reviews</a></div>', "\n";
    }
    /*
     * New function to enable VM integration
     */
    private function print_vm_interface(Contact $user) {

        include_once('src/pve_api/pve_functions.php');
        $conf = $user->conf;
        $any_open = false;
        if ($user->is_empty()) {
            return;
        }

        echo '<div class="homegrp" id="homeau">',
            $this->print_h2_home("Artifact Evaluation VMs");
        echo '            <button onClick="location.reload();"><span style="color:gray" align="center"><b>&#x1f5d8;</b></span>Reload</button>';

        if (($email = trim($user->email ?? "")) === "") {
            return JsonResult::make_missing_error("email");
        }

        if (!($db = $user->conf->contactdb())) {
            $db = $user->conf->dblink;
        }
        $result = Dbl::qe($db, "select contactId from ContactInfo where email = ? and not disabled order by email asc limit 1", $email);
        $vms = array();
        
        foreach ($result as $cidkey => $cid) {
            if ($user->is_admin()) {
                $vmdata = Dbl::qe($db, "select * from VMs where active = 1");

            } else {
                $vmdata = Dbl::qe($db, "SELECT * FROM VMs as v left join VMaccess as vma on v.vmId = vma.vmId  WHERE contactId = ? AND active = 1 ORDER BY v.vmid;", $cid['contactId']);
            };
            foreach ($vmdata as $vm){
	    	    $vmid = $vm['v.vmid'];
		    $vmdesc = $vm['vmdesc'];
                $vmconfig = update_vm_config($vm['vmid'], $vmconfig, $db);
                $vm_status = get_vm_status($vm['vmid'], $vmconfig);
                
                // Find owner
                $vm_owner_qry = Dbl::qe($db, "select email from ContactInfo where contactId = ? and not disabled order by email asc limit 1", $vm['contactId']);
                $vm_owner = '';
                $vm_owner_self = false;
                foreach ($vm_owner_qry as $key => $vm_owner_mail){
                    if ($vm_owner_mail) {
                        if ($vm_owner_mail['email'] == $email) {
                            $vm_owner = '<b>'.$vm_owner_mail['email'].'</b>';
                            $vm_owner_self = true;
                        } else {
                            if ($user->is_admin()) {
                                $vm_owner = '<i>'.$vm_owner_mail['email'].'</i>';
                            } else {
                                // Always blinding author/reviewer names for now
                                $reviewer_data = Dbl::qe($db, "SELECT paperId from PaperReview WHERE paperId = ? AND contactId = ?;", $vm['paperId'], $vm['contactId']);
                                $rpaper = false;
                                foreach ($reviewer_data as $rkey => $rval) {
                                    $rpaper = true;
                                };
                                $author_data = Dbl::qe($db, "SELECT paperId FROM Paper WHERE authorInformation LIKE ".Dbl::utf8ci("'%\t?ls\t%'")." AND paperId = ?;", $vm_owner_mail['email'], $vm['paperId']);
                                $apaper = false;
                                foreach ($author_data as $rkey => $rval) {
                                    $apaper = true;
                                };
                                if ($rpaper) {
                                    $vm_owner = "<i>Anon. Reviewer</i>";
                                } elseif ($apaper) {
                                    $vm_owner = "<i>Anon. Author</i>";
                                } else {
                                    $vm_owner = "<i>Blind</i>";
                                };
                            };
                        };
                    }
                };
                
                $vms[$vm['vmid']] = array();
                $vms[$vm['vmid']]['type'] = $vm['vmtype'];
		$vms[$vm['vmid']]['status'] = 'active';	

                if ($vm_status['data']['status'] == 'running') {
                    $vm_ifstat = get_vm_ifstat($vm['vmid'], $vmconfig);

                    if ($vm_ifstat['mac'] && $vm_ifstat['ipv4'] && $vm_ifstat['ipv6']) {
                        $vmlog = Dbl::qe($db, "INSERT INTO UserVMLogs (vmid, createHash, contactId, mac, ipv4, ipv6 ) VALUES (?, ?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE seen_last = NOW()", $vm['vmid'], $vm['createHash'], $cid['contactId'], $vm_ifstat['mac'], $vm_ifstat['ipv4'], $vm_ifstat['ipv6']);
                    };
                    if ($vm_status['data']['uptime'] > 86400 ) {
                        $vms[$vm['vmid']]['uptime'] = gmdate("d\d H\h i\m s\s",$vm_status['data']['uptime']);
                    } else {
                        $vms[$vm['vmid']]['uptime'] = gmdate("H\h i\m s\s",$vm_status['data']['uptime']);
                    };
                    $vms[$vm['vmid']]['status'] = '<div title="Running" style="color:green" align="center">&#x25B6;</div>';
                    if ($vm_owner_self) {
                        $vms[$vm['vmid']]['actions'] = '
                            <a title="VM Console" class="vmbtn" href="startvm.php?action=console&vmid='.$vm['vmid'].'" target="_blank"><span class="vmbtn" style="color:black" align="center">&#x1F5B5;</span></a>
                            <a title="Power Off" class="vmbtn" onClick="trigger_vm_action(\'stop\', \''.$vm['vmid'].'\')" target="_blank"><span class="vmbtn" style="color:red" align="center">&#x23FC;</span></a>
                            <a title="Hard Reset" class="vmbtn" onClick="trigger_vm_action(\'reset\', \''.$vm['vmid'].'\')" target="_blank"><span class="vmbtn" style="color:black" align="center">&#x21ba</span></a>
                            <a title="Remove VM" class="vmbtn" onClick="trigger_vm_action(\'delete\', \''.$vm['vmid'].'\')" target="_blank"><span class="vmbtn" style="color:red" align="center">&#x1F5D9;</span></a>
                            <a title="Reset Password" class="vmbtn" href="startvm.php?action=resetpw&vmid='.$vm['vmid'].'" target="_blank"><span class="vmbtn" style="color:black" align="center"><b>?</b></span></a>
                        ';
                    } else {
                        $vms[$vm['vmid']]['actions'] = '
                            <a title="VM Console" class="vmbtn" href="startvm.php?action=console&vmid='.$vm['vmid'].'" target="_blank"><span class="vmbtn" style="color:black" align="center">&#x1F5B5;</span></a>
                            <span title="Power off (not allowed)" style="color:gray" align="center">&#x23FC;</span>
                            <span title="Hard Reset (not allowed)" style="color:gray" align="center">&#x21ba</span>
                            <span title="Remove VM (not allowed)" style="color:gray" align="center">&#x1F5D9;</span>
                            <a title="Reset Password" class="vmbtn" href="startvm.php?action=resetpw&vmid='.$vm['vmid'].'" target="_blank"><span class="vmbtn" style="color:black" align="center"><b>?</b></span></a>
                        ';
                    };
                } else {
                    $vms[$vm['vmid']]['status'] = '<div title="Stopped" style="color:gray" align="center">&#x23FC;</div>';
                    if ($vm_owner_self) {
                        $vms[$vm['vmid']]['actions'] = '
                            <a title="Start VM" class="vmbtn" onClick="trigger_vm_action(\'start\', \''.$vm['vmid'].'\')" target="_blank"><span class="vmbtn" style="color:green" align="center">&#x25B6;</span></a>
                            <a title="Remove VM" class="vmbtn" onClick="trigger_vm_action(\'delete\', \''.$vm['vmid'].'\')" target="_blank"><span class="vmbtn" style="color:red" align="center">&#x1F5D9;</span></a>
                        ';
                    } else {
                        $vms[$vm['vmid']]['actions'] = '
                            <span title="Start VM (not allowed)" style="color:gray" align="center">&#x25B6;</span>
                            <span title="Remove VM (not allowed)" style="color:gray" align="center">&#x1F5D9;</span>
                        ';
                    };
                };
                $vms[$vm['vmid']]['owner'] = $vm_owner;
                if ($vms[$vm['vmid']]['owner'] == '<b>'.$email.'</b>') {
                    $vms[$vm['vmid']]['prefix'] = Ht::unstash();
                    $vms[$vm['vmid']]['prefix'] .= $this->conf->make_script_file("scripts/vmsettings.js")."\n";
                };
                $vms[$vm['vmid']]['paper'] = 'none';
                $vm_paper_id = 'none';
                if ($vm['paperId']) {
                    $vm_paper_id = $vm['paperId'];
                };
                if ($vms[$vm['vmid']]['owner'] == '<b>'.$email.'</b>') {
                    
                    $vms[$vm['vmid']]['paper'] = '            <select name="paperidvm'.$vm['vmid'].'" id="paperidvm'.$vm['vmid'].'" onChange="'."vmif_toggle_button_visibility('update".$vm['vmid']."', 'initval".$vm_paper_id."', 'paperid', this)".';">';
                    $vms[$vm['vmid']]['paper'] .= '                <option value="-">-</option> ';
                    $user_papers = array();
                    if ($user->is_author()) {
                        $plist = $user->authored_papers();
                        foreach ($plist as $idx => $paper) {
                            $user_papers[$paper->paperId] = "(Author)";
                        }
                    }
                    if ($user->is_reviewer()) {
                        $plist = $user->papers_for_review();
                        foreach ($plist as $idx => $paper) {
                            $user_papers[$paper->paperId] = "(Reviewer)";
                        }
                    }
                    ksort($user_papers);
                    foreach ($user_papers as $paperId => $paperType) {
                        if ($paperId == $vm_paper_id) {
                            $vms[$vm['vmid']]['paper'] .= '                <option value="'.$paperId.'" selected>'.$paperId.' '.$paperType.'</option> ';
                        } else {
                            $vms[$vm['vmid']]['paper'] .= '                <option value="'.$paperId.'">'.$paperId.' '.$paperType.'</option> ';
                        }
                    }
                    $vms[$vm['vmid']]['paper'] .= '            </select>';
                } else {
                    $vms[$vm['vmid']]['paper'] = $vm_paper_id;
                };

                $vm_reviewer_access = False;
                if ($vm['reviewerVisible']) {
                    $vm_reviewer_access = $vm['reviewerVisible'];
                };
                if ($vms[$vm['vmid']]['owner'] == '<b>'.$email.'</b>') {
                    $vms[$vm['vmid']]['button'] = Ht::submit("update".$vm['vmid'], "Save", ["class" => "btn-primary hidden", "type" => "submit", "onClick" => "vmif_submit_update('".$vm['vmid']."', this);"]);
                    $vms[$vm['vmid']]['button'] .= "</form>\n";
                };
            };
        };
        
        if (!$vms) {
            echo '<span class="hint">You currently do not have running VMs. Use the Interface below to start a new VM!</span>';
        };
        echo '<div id="foldre" class="homesubgrp">
                    <table class="pltable pltable-reviewerhome has-hotlist fold2c fold4c fold7c fold8c">';

        echo '  
                    <tr class="pl_headrow">
                    <th class="pl plh pl_id" data-pc="id">ID
                    </th>
                    <th class="pl plh pl_type" data-pc="type">Type
                    </th>
                    <th class="pl plh pl_pl_uptime" data-pc="pl_uptime">Uptime
                    </th>
                    <th class="pl plh pl_status pl-status" data-pc="status">Actions
                    </th>
                    <th class="pl plh pl_status pl-status" data-pc="status">Owner
                    </th>
                    <th class="pl plh pl_status pl-status" data-pc="status">Paper#
                    </th>
                    </tr>';
        echo ' </thead>';
        echo '  <tbody class="pltable pltable-colored">';
        $tag = '';
        foreach ($vms as $vmid => $vminfo ){
	
            echo '<tr class="pl '.$tag.' k0 plnx" data-pid="'.$vmid.'" data-tags="'.$vms[$vmid]["fqdn"].'">'."\n";
            echo '<td class="pl pl_id">';
            echo $vmid; 
            echo '</td>';
            if ($tag == '') {
                $tag = 'tag-gray';
            } else {
                $tag = '';
            };
            foreach ($vms[$vmid] as $vmpropname => $vmprop ) {
                if ($vmpropname == 'paper') {
                    echo '<td class="pl pl_'.$vmpropname.'">';
                    echo $vmprop;
                    echo '</td>';
                } elseif ($vmpropname == 'prefix') {
                    echo $vmprop;
                } else {
                    echo '<td class="pl pl_'.$vmpropname.'">';
                    echo $vmprop;
                    echo '</td>';
                };
            };
            echo '</tr>';
        };
        echo '  </tbody>';
        echo '</table></div></div>';
    }

    private function print_new_submission(Contact $user, SubmissionRound $sr) {
        $conf = $user->conf;
        if ($sr->register >= Conf::$now && $sr->register < $sr->submit) {
            $dname = $conf->_5("<5>{sclass} registration deadline", new FmtArg("sclass", $sr->tag, 0));
            $dtime = $conf->unparse_time_with_local_span($sr->register);
            $dltx = "<em class=\"deadline\">{$dname}: {$dtime}</em>";
        } else if ($sr->submit > 0) {
            $dname = $conf->_5("<5>{sclass} deadline", new FmtArg("sclass", $sr->tag, 0));
            $dtime = $conf->unparse_time_with_local_span($sr->submit);
            $dltx = "<em class=\"deadline\">{$dname}: {$dtime}</em>";
        } else {
            $dltx = "";
        }
        if ($user->has_email()) {
            $url = $conf->hoturl("paper", [
                "p" => "new", "sclass" => $sr->unnamed ? null : $sr->tag
            ]);
            $actions = [[
                "<a class=\"btn\" href=\"{$url}\">" . $conf->_c5("paper_edit", "<0>New {sclass} {submission}", new FmtArg("sclass", $sr->tag)) . "</a>",
                $sr->time_register(true) ? "" : "(admin only)"
            ]];
            if ($dltx !== "") {
                $actions[] = [$dltx];
            }
            echo Ht::actions($actions, ["class" => "aab mt-0 mb-2 align-items-baseline"]);
        } else if ($dltx !== "") {
            echo '<p class="mb-2">', $dltx, '</p>';
        }
    }

    private function submission_round_deadlines(&$deadlines, SubmissionRound $sr) {
        if (!$sr->time_submit(true)) {
            // Be careful not to refer to a future deadline; perhaps an admin
            // just turned off submissions.
            if (!$sr->submit || $sr->submit + $sr->grace > Conf::$now) {
                $deadlines[] = "The site is not open for {$sr->title1}{$this->conf->snouns[1]} at the moment.";
            } else {
                $deadlines[] = 'The <a href="' . $this->conf->hoturl("deadlines") . "\">{$sr->title1}{$this->conf->snouns[0]} deadline</a> has passed.";
            }
        } else if (!$sr->time_update(true)) {
            $deadlines[] = 'The <a href="' . $this->conf->hoturl("deadlines") . "\">{$sr->title1}update deadline</a> has passed, but you can still submit.";
            if ($sr->submit > Conf::$now) {
                $d = $this->conf->unparse_time_with_local_span($sr->submit);
                $deadlines[] = "You have until {$d} to submit {$sr->title1}papers.";
            }
        } else {
            if ($sr->update > Conf::$now) {
                $d = $this->conf->unparse_time_with_local_span($sr->update);
                $deadlines[] = "You have until {$d} to submit {$sr->title1}papers.";
            }
        }
    }

    function print_submissions(Contact $user, Qrequest $qreq, $gx) {
        $conf = $user->conf;
        $srlist = [];
        $any_open = false;
        $sl = $conf->site_lock("paper:start");
        if ($sl === 0 || ($sl === 1 && $user->is_manager())) {
            foreach ($conf->submission_round_list() as $sr) {
                $any_open = $any_open || $sr->open > 0;
                if ($user->privChair || $sr->time_register(true)) {
                    $srlist[] = $sr;
                }
            }
        }
        if (!$user->is_author()
            && !$user->privChair
            && $user->is_reviewer()
            && empty($srlist)) {
            return;
        }

        if ($user->is_author()) {
            $t = $conf->_c("home", "<0>Your {Submissions}");
        } else {
            $t = $conf->_c("home", "<0>{Submissions}");
        }
        echo '<div class="homegrp" id="homeau">', $this->print_h2_home(Ftext::as(5, $t));

        if (!empty($srlist)) {
            if (!$user->has_email()) {
                echo "<p>", Ht::link("Sign in", $conf->hoturl("signin")),
                    " to manage {$conf->snouns[1]}.</p>";
            }
            usort($srlist, "SubmissionRound::compare");
            foreach ($srlist as $sr) {
                $this->print_new_submission($user, $sr);
            }
        }

        $plist = null;
        if ($user->is_author()) {
            $plist = new PaperList("authorHome", new PaperSearch($user, ["t" => "a"]));
            if (!$plist->is_empty()) {
                $plist->set_table_decor(PaperList::DECOR_LIST);
                $plist->print_table_html();
            }
        }

        $deadlines = [];
        if ($plist && !$plist->is_empty()) {
            $dlr = [];
            foreach ($plist->rowset() as $prow) {
                if ($prow->timeSubmitted > 0 || $prow->timeWithdrawn > 0) {
                    continue;
                }
                $sr = $prow->submission_round();
                if (isset($dlr[$sr->tag])) {
                    continue;
                }
                $dlr[$sr->tag] = true;
                $this->submission_round_deadlines($deadlines, $sr);
            }
        }
        if (empty($srlist) && empty($deadlines)) {
            if ($any_open) {
                $deadlines[] = "The <a href=\"" . $conf->hoturl("deadlines") . "\">deadline</a> for registering {$conf->snouns[1]} has passed.";
            } else {
                $deadlines[] = "The site is not open for {$conf->snouns[1]} at the moment.";
            }
        }
        // NB only has("accepted") if author can see an accepted paper
        if ($plist && $plist->has("accepted")) {
            $d = $this->setting_time_span("final_soft");
            if ($conf->time_after_setting("final_soft") && $plist->has("need_final")) {
                $deadlines[] = "<strong class=\"overdue\">Final versions are overdue.</strong> They were requested by {$d}.";
            } else if ($d) {
                $deadlines[] = "Submit final versions of your accepted papers by {$d}.";
            }
        }
        // add full code for checking users' access to the VM feature here.
        if (!empty($deadlines)) {
            if ($plist && !$plist->is_empty()) {
                echo '<hr class="g">';
            }
            echo '<p>', join("<br>\n", $deadlines), "</p>";
        }

        echo "</div>\n";
	if (!$user->is_reviewer()) {
		echo "<!-- this is a hidden comment for debugging -->";
        	$this->print_vm_interface($user);
	}
    }
}
