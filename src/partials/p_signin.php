<?php
// src/partials/p_signin.php -- HotCRP password reset partials
// Copyright (c) 2006-2020 Eddie Kohler; see LICENSE.

class Signin_Partial {
    private $_reset_cap;
    private $_reset_capdata;
    private $_reset_user;

    private function bad_post_error(Contact $user, Qrequest $qreq, $action) {
        $sid = session_id();
        $msg = "{$user->conf->dbname}: ignoring unvalidated $action"
            . ", sid=" . ($sid === "" ? ".empty" : $sid);
        if ($qreq->email) {
            $msg .= ", email=" . $qreq->email;
        }
        if ($qreq->password) {
            $msg .= ", password";
        }
        if (isset($_GET["post"])) {
            $msg .= ", post=" . $_GET["post"];
        }
        error_log($msg);

        $user->conf->msg($user->conf->_i("badpost"), 2);
    }

    static private function forgot_message(Conf $conf) {
        return $conf->_("Enter your email and we’ll send you instructions for signing in.");
    }


    // Signin request
    static function signin_request(Contact $user, Qrequest $qreq) {
        assert($qreq->method() === "POST");
        if ($qreq->cancel) {
            Navigation::redirect();
        } else if ($user->conf->opt("httpAuthLogin")) {
            LoginHelper::check_http_auth($user, $qreq);
        } else if ($qreq->post_ok()) {
            if (!$user->is_empty() && strcasecmp($qreq->email, $user->email) === 0) {
                Navigation::redirect();
            } else if (!$qreq->start) {
                if ($user->conf->external_login()) {
                    $info = LoginHelper::external_login_info($user->conf, $qreq);
                } else {
                    $info = LoginHelper::login_info($user->conf, $qreq);
                }
                if ($info["ok"]) {
                    Navigation::redirect(get($info, "redirect"));
                } else {
                    LoginHelper::login_error($user->conf, $qreq->email, $info);
                }
            }
        } else {
            self::bad_post_error($user, $qreq, "signin");
        }
    }

    function render_signin_head(Contact $user, Qrequest $qreq, $gx) {
        ensure_session();
        $user->conf->header("Sign in", "home");
        $gx->push_render_cleanup("__footer");
    }

    static function render_signin_form(Contact $user, Qrequest $qreq, $gx) {
        global $Now;
        $conf = $user->conf;
        if (($password_reset = $user->session("password_reset"))) {
            if ($password_reset->time < $Now - 900) {
                $user->save_session("password_reset", null);
            } else if (!isset($qreq->email)) {
                $qreq->email = $password_reset->email;
            }
        }

        $unfolded = $gx->root === "signin" || $qreq->signin;
        echo '<div class="homegrp fold', ($unfolded ? "o" : "c"),
            '" id="homeacct">',
            Ht::form($conf->hoturl("signin"), ["class" => "ui-submit uin js-signin compact-form"]),
            Ht::hidden("post", post_value(true));
        if (!$unfolded) {
            echo Ht::unstash_script('fold("homeacct",false)');
        }

        $gx->render_group("signin/form");

        echo '<div class="popup-actions fx">',
            Ht::submit("", "Sign in", ["id" => "signin_signin", "class" => "btn-success", "tabindex" => 1]);
        if ($gx->root !== "home") {
            echo Ht::submit("cancel", "Cancel", ["tabindex" => 1]);
        }
        echo '</div>';
        if ($conf->allow_user_self_register()) {
            echo '<p class="mt-2 hint fx">New to the site? <a href="',
                $conf->hoturl("newaccount"),
                '" class="uic js-href-add-email">Create an account</a></p>';
        }

        if (!$unfolded) {
            echo '<div class="fn">',
                Ht::submit("start", "Sign in", ["class" => "btn-success", "tabindex" => 1, "value" => 1]),
                '</div>';
        }
        echo '</form></div>';
    }

    static function render_signin_form_description(Contact $user, Qrequest $qreq) {
        echo '<p class="mb-5">',
            $user->conf->_("Sign in to submit or review papers."), '</p>';
        if ($user->conf->opt("contactdb_dsn")
            && ($x = $user->conf->opt("contactdb_loginFormHeading"))) {
            echo $x;
        }
    }

    static function render_signin_form_email(Contact $user, Qrequest $qreq, $gx) {
        $is_external_login = $user->conf->external_login();
        echo '<div class="', Ht::control_class("email", "f-i fx"), '">',
            Ht::label($is_external_login ? "Username" : "Email", "signin_email"),
            Ht::entry("email", (string) $qreq->email, [
                "size" => 36, "id" => "signin_email", "class" => "fullw",
                "autocomplete" => "username", "tabindex" => 1,
                "type" => !$is_external_login && !str_ends_with($qreq->email, "@_.com") ? "email" : "text",
                "autofocus" => Ht::problem_status_at("email")
                    || !$qreq->email
                    || (!Ht::problem_status_at("password") && !$user->session("password_reset"))
            ]),
            Ht::render_messages_at("email"), '</div>';
    }

    static function render_signin_form_password(Contact $user, Qrequest $qreq, $gx) {
        $is_external_login = $user->conf->external_login();
        echo '<div class="', Ht::control_class("password", "f-i fx"), '">';
        if (!$is_external_login) {
            echo '<div class="float-right"><a href="',
                $user->conf->hoturl("forgotpassword"),
                '" class="n x small uic js-href-add-email">Forgot your password?</a></div>';
        }
        $password_reset = $user->session("password_reset");
        echo Ht::label("Password", "signin_password"),
            Ht::password("password",
            Ht::problem_status_at("password") !== 1 ? "" : $qreq->password, [
                "size" => 36, "id" => "signin_password", "class" => "fullw",
                "autocomplete" => "current-password", "tabindex" => 1,
                "autofocus" => !Ht::problem_status_at("email")
                    && $qreq->email
                    && (Ht::problem_status_at("password") || $password_reset)
            ]),
            Ht::render_messages_at("password"), '</div>';
        if ($password_reset) {
            echo Ht::unstash_script("\$(function(){\$(\"#signin_password\").val(" . json_encode_browser($password_reset->password) . ")})");
        }
    }


    // signout
    static function signout_request(Contact $user, Qrequest $qreq) {
        assert($qreq->method() === "POST");
        if ($qreq->cancel) {
            Navigation::redirect();
        } else if ($qreq->post_ok()) {
            LoginHelper::logout($user, true);
            Navigation::redirect($user->conf->hoturl("index", "signedout=1"));
        } else {
            self::bad_post_error($user, $qreq, "signout");
        }
    }

    static function render_signout_head(Contact $user, Qrequest $qreq, $gx) {
        ensure_session();
        $user->conf->header("Sign out", "signout", ["action_bar" => false]);
        $gx->push_render_cleanup("__footer");
    }
    static function render_signout_body(Contact $user, Qrequest $qreq, $gx) {
        echo '<div class="homegrp" id="homeaccount">',
            Ht::form($user->conf->hoturl("signout"), ["class" => "compact-form"]),
            Ht::hidden("post", post_value());
        if ($user->is_empty()) {
            echo '<div class="mb-5">',
                $user->conf->_("You are not signed in."),
                " ", Ht::link("Return home", $user->conf->hoturl("index")),
                '</div>';
        } else {
            echo '<div class="mb-5">',
                $user->conf->_("Use this page to sign out of the site."),
                '</div><div class="popup-actions">',
                Ht::submit("go", "Sign out", ["class" => "btn-danger float-left", "value" => 1]),
                Ht::submit("cancel", "Cancel", ["class" => "float-left"]),
                '</div>';
        }
        echo '</form></div>';
    }


    // newaccount
    static private function mail_user(Conf $conf, $info) {
        $user = $info["user"];
        $prep = $user->send_mail($info["mailtemplate"], get($info, "mailrest"));
        if (!$prep)  {
            if ($conf->opt("sendEmail")) {
                $conf->msg("The email address you provided seems invalid. Please try again.", 2);
                Ht::error_at("email");
            } else {
                $conf->msg("The system cannot send email at this time. You’ll need help from the site administrator to sign in.", 2);
            }
        } else if ($info["mailtemplate"] === "@newaccount") {
            $conf->msg("Sent mail to " . htmlspecialchars($user->email) . ". When you receive that mail, follow the link to set an initial password and sign in to the site.", "xconfirm");
        } else {
            $conf->msg("Sent mail to " . htmlspecialchars($user->email) . ". When you receive that mail, follow the link to reset your password.", "xconfirm");
            if ($prep->reset_capability) {
                $conf->log_for($user, null, "Password link sent " . substr($prep->reset_capability, 0, 8) . "...");
            }
        }
        return $prep;
    }

    static private function _render_email_entry($user, $qreq, $k) {
        echo '<div class="', Ht::control_class($k, "f-i"), '">',
            '<label for="', $k, '">',
            ($k === "email" ? "Email" : "Email or password reset code"),
            '</label>',
            Ht::entry($k, $qreq[$k], [
                "size" => 36, "id" => $k, "class" => "fullw",
                "autocomplete" => $k === "email" ? $k : null,
                "type" => $k === "email" ? $k : "text",
                "autofocus" => true
            ]),
            Ht::render_messages_at("resetcap"),
            Ht::render_messages_at("email"), '</div>';
    }

    static private function _create_message(Conf $conf) {
        return $conf->_("Enter your email and we’ll create an account and send you instructions for signing in.");
    }
    function create_request(Contact $user, Qrequest $qreq) {
        assert($qreq->method() === "POST");
        $conf = $user->conf;
        ensure_session();
        if ($qreq->cancel) {
            Navigation::redirect();
        } else if (!$user->conf->allow_user_self_register()) {
            // do nothing
        } else if ($qreq->post_ok()) {
            $info = LoginHelper::new_account_info($user->conf, $qreq);
            if ($info["ok"]) {
                $prep = self::mail_user($user->conf, $info);
                if ($prep
                    && $prep->reset_capability
                    && isset($info["firstuser"])) {
                    $conf->msg("As the first user, you have been assigned system administrator privilege. Use this screen to set a password. All later users will have to sign in normally.", "xconfirm");
                    Navigation::redirect($conf->hoturl("resetpassword/" . urlencode($prep->reset_capability)));
                } else if ($prep) {
                    Navigation::redirect($conf->hoturl("signin"));
                }
            } else {
                LoginHelper::login_error($user->conf, $qreq->email, $info);
            }
        } else {
            self::bad_post_error($user, $qreq, "newaccount");
        }
    }
    static function render_create_head(Contact $user, Qrequest $qreq, $gx) {
        $user->conf->header("New account", "newaccount", ["action_bar" => false]);
        $gx->push_render_cleanup("__footer");
        if (!$user->conf->allow_user_self_register()) {
            $user->conf->msg("New users can’t self-register for this site.", 2);
            echo '<p class="mb-5">', Ht::link("Return home", $user->conf->hoturl("index")), '</p>';
            return false;
        }
    }
    function render_create_body(Contact $user, Qrequest $qreq, $gx, $gj) {
        echo '<div class="homegrp" id="homeaccount">',
            Ht::form($user->conf->hoturl("newaccount"), ["class" => "compact-form"]),
            Ht::hidden("post", post_value());
        if (($m = self::_create_message($user->conf))) {
            echo '<p class="mb-5">', $m, '</p>';
        }
        self::_render_email_entry($user, $qreq, "email");
        echo '<div class="popup-actions">',
            Ht::submit("go", "Create account", ["class" => "btn-success", "value" => 1]),
            Ht::submit("cancel", "Cancel"),
            '</div>';
        echo '</form></div>';
        Ht::stash_script("focus_within(\$(\"#homeaccount\"));window.scroll(0,0)");
    }


    // Forgot password request
    static function forgot_externallogin_message(Contact $user) {
        $user->conf->msg("Password reset links aren’t used for this site. Contact your system administrator if you’ve forgotten your password.", 2);
        echo '<p class="mb-5">', Ht::link("Return home", $user->conf->hoturl("index")), '</p>';
        return false;
    }
    static function forgot_request(Contact $user, Qrequest $qreq) {
        assert($qreq->method() === "POST");
        if ($qreq->cancel) {
            Navigation::redirect();
        } else if ($qreq->post_ok()) {
            $info = LoginHelper::forgot_password_info($user->conf, $qreq);
            if ($info["ok"]) {
                self::mail_user($user->conf, $info);
                Navigation::redirect(get($info, "redirect", $qreq->annex("redirect")));
            } else {
                LoginHelper::login_error($user->conf, $qreq->email, $info);
            }
        } else {
            self::bad_post_error($user, $qreq, "forgot");
        }
    }
    static function render_forgot_head(Contact $user, Qrequest $qreq, $gx) {
        $user->conf->header("Forgot password", "resetpassword", ["action_bar" => false]);
        $gx->push_render_cleanup("__footer");
        if ($user->conf->external_login()) {
            return $gx->render("forgotpassword/__externallogin");
        }
    }
    static function render_forgot_body(Contact $user, Qrequest $qreq, $gx, $gj) {
        echo '<div class="homegrp" id="homeaccount">',
            Ht::form($user->conf->hoturl("forgotpassword"), ["class" => "compact-form"]),
            Ht::hidden("post", post_value()),
            '<p class="mb-5">', self::forgot_message($user->conf), '</p>';
        self::_render_email_entry($user, $qreq, "email");
        echo '<div class="popup-actions">',
            Ht::submit("go", "Reset password", ["class" => "btn-primary", "value" => 1]),
            Ht::submit("cancel", "Cancel"),
            '</div></form></div>';
        Ht::stash_script("focus_within(\$(\"#homeaccount\"));window.scroll(0,0)");
    }


    // Password reset
    function reset_request(Contact $user, Qrequest $qreq) {
        global $Now;
        ensure_session();
        $conf = $user->conf;
        if ($qreq->cancel) {
            Navigation::redirect();
        } else if ($conf->external_login()) {
            return;
        }

        if ($qreq->resetcap === null
            && preg_match('/\A\/(U?1[-\w]+)(?:\/|\z)/', $qreq->path(), $m)) {
            $qreq->resetcap = $m[1];
        }

        $resetcap = trim((string) $qreq->resetcap);
        if ($resetcap === "") {
            // nothing
        } else if (strpos($resetcap, "@") !== false) {
            if ($qreq->go
                && $qreq->method() === "POST"
                && $qreq->post_ok()) {
                $nqreq = new Qrequest("POST", ["email" => $resetcap]);
                $nqreq->approve_post();
                $nqreq->set_annex("redirect", $user->conf->hoturl("resetpassword"));
                self::forgot_request($user, $nqreq); // may redirect
                if (Ht::problem_status_at("email")) {
                    Ht::error_at("resetcap");
                }
            }
        } else {
            if (preg_match('{\A/?(U?1[-\w]+)/?\z}', $resetcap, $m)) {
                $this->_reset_cap = $m[1];
            }
            if ($this->_reset_cap) {
                $capmgr = $conf->capability_manager($this->_reset_cap);
                $this->_reset_capdata = $capmgr->check($this->_reset_cap);
            }
            if (!$this->_reset_capdata
                || $this->_reset_capdata->capabilityType != CAPTYPE_RESETPASSWORD) {
                Ht::error_at("resetcap", "Unknown or expired password reset code. Please check that you entered the code correctly.");
                $this->_reset_capdata = null;
            }
        }

        if ($this->_reset_capdata) {
            if (str_starts_with($this->_reset_cap, "U")) {
                $this->_reset_user = $conf->contactdb_user_by_id($this->_reset_capdata->contactId);
            } else {
                $this->_reset_user = $conf->user_by_id($this->_reset_capdata->contactId);
            }
        }

        if ($this->_reset_user
            && $qreq->go
            && $qreq->method() === "POST"
            && $qreq->post_ok()) {
            $p1 = (string) $qreq->password;
            $p2 = (string) $qreq->password2;
            if ($p1 === "") {
                if ($p2 !== "" || $qreq->autopassword) {
                    Ht::error_at("password", "Password required.");
                }
            } else if (trim($p1) !== $p1) {
                Ht::error_at("password", "Passwords cannot begin or end with spaces.");
                Ht::error_at("password2");
            } else if (strlen($p1) < 4) {
                Ht::error_at("password", "Password too short.");
                Ht::error_at("password2");
            } else if (!Contact::valid_password($p1)) {
                Ht::error_at("password", "Invalid password.");
                Ht::error_at("password2");
            } else if ($p1 !== $p2) {
                Ht::error_at("password", "The passwords you entered did not match.");
                Ht::error_at("password2");
            } else {
                $accthere = $conf->user_by_email($this->_reset_user->email)
                    ? : Contact::create($conf, null, $this->_reset_user);
                $accthere->change_password($p1);
                $accthere->log_activity("Password reset via " . substr($this->_reset_cap, 0, 8) . "...");
                $conf->msg("Password changed. Use the new password to sign in below.", "xconfirm");
                $capmgr->delete($this->_reset_capdata);
                $user->save_session("password_reset", (object) [
                    "time" => $Now, "email" => $this->_reset_user->email, "password" => $p1
                ]);
                Navigation::redirect($conf->hoturl("signin"));
            }
        } else if (!$this->_reset_user
                   && $this->_reset_capdata) {
            Ht::error_at("resetcap", "This password reset code refers to a user who no longer exists. Either create a new account or contact the conference administrator.");
        }
    }
    function render_reset_head(Contact $user, Qrequest $qreq, $gx, $gj) {
        $user->conf->header($gj->htitle, "resetpassword", ["action_bar" => false]);
        $gx->push_render_cleanup("__footer");
        if ($user->conf->external_login()) {
            return $gx->render("forgotpassword/__externallogin");
        }
    }
    private function _render_reset_success($user, $qreq) {
        if (!isset($qreq->autopassword)
            || trim($qreq->autopassword) !== $qreq->autopassword
            || strlen($qreq->autopassword) < 16
            || !preg_match('{\A[-0-9A-Za-z@_+=]*\z}', $qreq->autopassword)) {
            $qreq->autopassword = hotcrp_random_password();
        }
        echo Ht::hidden("resetcap", $this->_reset_cap),
            Ht::hidden("autopassword", $qreq->autopassword),
            '<p class="mb-5">Use this form to reset your password. You may want to use the random password we’ve chosen.</p>',
            '<div class="f-i"><label>Email</label>', htmlspecialchars($this->_reset_user->email), '</div>',
            Ht::entry("email", $this->_reset_user->email, ["class" => "hidden", "autocomplete" => "username"]),
            '<div class="f-i"><label>Suggested strong password</label>',
            htmlspecialchars($qreq->autopassword), '</div>',

            '<div class="', Ht::control_class("password", "f-i"), '">',
            '<label for="password">New password</label>',
            Ht::password("password", "", ["class" => "fullw", "size" => 36, "id" => "password", "autocomplete" => "new-password", "autofocus" => true]),
            Ht::render_messages_at("password"),
            '</div>',

            '<div class="', Ht::control_class("password2", "f-i"), '">',
            '<label for="password2">Repeat new password</label>',
            Ht::password("password2", "", ["class" => "fullw", "size" => 36, "id" => "password2", "autocomplete" => "new-password"]),
            Ht::render_messages_at("password2"),
            '</div>';
    }
    function render_reset_body(Contact $user, Qrequest $qreq, $gx, $gj) {
        echo '<div class="homegrp" id="homeaccount">',
            Ht::form($user->conf->hoturl("resetpassword"), ["class" => "compact-form"]),
            Ht::hidden("post", post_value());
        if ($this->_reset_user) {
            $this->_render_reset_success($user, $qreq);
            $k = "btn-danger";
        } else {
            echo '<p class="mb-5">', self::forgot_message($user->conf),
                ' Or enter a password reset code if you have one.</p>';
            self::_render_email_entry($user, $qreq, "resetcap");
            $k = "btn-primary";
        }
        echo '<div class="popup-actions">',
            Ht::submit("go", "Reset password", ["class" => $k, "value" => 1]),
            Ht::submit("cancel", "Cancel"),
            '</div></form></div>';
        Ht::stash_script("focus_within(\$(\"#homeaccount\"));window.scroll(0,0)");
    }
}