import app from 'flarum/forum/app';
import { extend } from 'flarum/common/extend';
import CommentPost from 'flarum/forum/components/CommentPost';
import Icon from 'flarum/common/components/Icon';
import extractText from 'flarum/common/utils/extractText';
import Switch from 'flarum/common/components/Switch';
import FieldSet from 'flarum/common/components/FieldSet';

const t = (key, params) => app.translator.trans(`ernestdefoe-nameplate.forum.${key}`, params);
const on = (attr) => app.forum.attribute(attr) !== false;

/**
 * The member's ranks: their visible groups. Ladder's ranks ARE groups, so a
 * forum running Ladder gets its ranks here without either knowing the other.
 */
function ranks(user) {
  let groups = [];
  try {
    groups = (user.groups() || []).filter((g) => g && !g.isHidden());
  } catch (e) {
    return [];
  }
  return groups.slice(0, 2);
}

/** The facts, in the order a traditional forum lists them, each only if known and switched on. */
function facts(user) {
  const out = [];
  const num = (n) => Number(n).toLocaleString();

  if (on('nameplatePosts') && typeof user.commentCount() === 'number') {
    out.push({ key: 'posts', icon: 'far fa-comment', label: t('posts'), value: num(user.commentCount()) });
  }
  if (on('nameplateDiscussions') && typeof user.discussionCount() === 'number') {
    out.push({ key: 'discussions', icon: 'far fa-comments', label: t('discussions'), value: num(user.discussionCount()) });
  }
  const likes = user.attribute('nameplateLikesReceived');
  if (on('nameplateLikes') && typeof likes === 'number') {
    out.push({ key: 'likes', icon: 'far fa-thumbs-up', label: t('likes'), value: num(likes) });
  }
  const best = user.attribute('bestAnswerCount');
  if (on('nameplateBestAnswers') && typeof best === 'number' && best > 0) {
    out.push({ key: 'best', icon: 'fas fa-check', label: t('best_answers'), value: num(best) });
  }
  if (on('nameplateJoined') && user.joinTime()) {
    out.push({ key: 'joined', icon: 'far fa-calendar', label: t('joined'), value: dayjs(user.joinTime()).format('MMM YYYY') });
  }

  return out;
}

function rankLabel(group) {
  const color = group.color();
  return (
    <span className="Nameplate-rank" style={color ? { '--nameplate-rank': color } : undefined}>
      {group.icon() ? <Icon name={group.icon()} /> : null}
      {group.nameSingular()}
    </span>
  );
}

/** The classic panel, under the avatar in the post's side column. */
function panel(user) {
  const list = facts(user);
  const rankList = on('nameplateRank') ? ranks(user) : [];
  if (!list.length && !rankList.length) return null;

  return (
    <div className="Nameplate">
      {rankList.length ? <div className="Nameplate-ranks">{rankList.map(rankLabel)}</div> : null}
      {list.length ? (
        <dl className="Nameplate-facts">
          {list.map((f) => (
            <div className={`Nameplate-fact Nameplate-fact--${f.key}`}>
              <dt>{f.label}</dt>
              <dd>{f.value}</dd>
            </div>
          ))}
        </dl>
      ) : null}
    </div>
  );
}

/**
 * The same facts as one line under the name — the compact layout, and what a
 * phone shows, where there is no side column to put a panel in.
 */
function line(user) {
  const list = facts(user);
  const rankList = on('nameplateRank') ? ranks(user) : [];
  if (!list.length && !rankList.length) return null;

  return (
    <span className="Nameplate-line">
      {rankList.map(rankLabel)}
      {list.map((f) => (
        <span className={`Nameplate-lineFact Nameplate-fact--${f.key}`} title={extractText(f.label)}>
          <Icon name={f.icon} /> {f.value}
        </span>
      ))}
    </span>
  );
}

/**
 * Which signatures this post should hide.
 *
 * The first post a member has in the loaded thread keeps theirs; later ones
 * drop it. Posts load in any order — a jump to #50 renders before #10 — so the
 * earliest number SEEN so far wins, and a later post redraws without its
 * signature once an earlier one by the same member arrives.
 */
const firstPost = new Map();
function repeatsSignature(post) {
  const user = post.user && post.user();
  const discussion = post.discussion && post.discussion();
  if (!user || !discussion) return false;

  const key = `${discussion.id()}:${user.id()}`;
  const number = post.number();
  const first = firstPost.get(key);

  if (first === undefined || number < first) {
    firstPost.set(key, number);
    return false;
  }

  return number > first;
}

app.initializers.add('ernestdefoe-nameplate', () => {
  extend(CommentPost.prototype, 'sideItems', function (items) {
    if (app.forum.attribute('nameplateLayout') === 'header') return;
    const user = this.attrs.post.user();
    if (!user) return;

    const block = panel(user);
    if (block) items.add('nameplate', block, 90);
  });

  extend(CommentPost.prototype, 'headerItems', function (items) {
    const user = this.attrs.post.user();
    if (!user) return;

    const block = line(user);
    if (block) items.add('nameplate', block, 95);
  });

  /*
   * 🚨 On the post's own attributes, which Flarum keeps, not as a class added
   * to its element afterwards — Mithril rewrites the class list on the next
   * redraw and an added class vanishes.
   */
  extend(CommentPost.prototype, 'elementAttrs', function (attrs) {
    const classes = [attrs.className || ''];

    if (app.forum.attribute('nameplateLayout') !== 'header') classes.push('Nameplate--side');

    const me = app.session.user;
    const hideAll = me && me.preferences() && me.preferences().nameplateHideSignatures;
    if (hideAll || (on('nameplateSignatureOnce') && repeatsSignature(this.attrs.post))) {
      classes.push('Nameplate--noSignature');
    }

    attrs.className = classes.filter(Boolean).join(' ');
  });

  /*
   * The member's own switch, under Settings → Reading. Only on forums running
   * FoF Signature: on any other it would switch off something that is not
   * there. By path, because Flarum 2 loads the settings page on demand.
   */
  extend('flarum/forum/components/SettingsPage', 'settingsItems', function (items) {
    if (!('fof-signature' in (flarum.extensions || {}))) return;

    const user = this.user;

    items.add(
      'nameplate',
      <FieldSet className="Settings-nameplate FieldSet--min" label={t('settings.heading')}>
        <Switch
          state={!!(user.preferences() || {}).nameplateHideSignatures}
          loading={this.nameplateSaving}
          onchange={(value) => {
            this.nameplateSaving = true;
            user.savePreferences({ nameplateHideSignatures: value }).then(() => {
              this.nameplateSaving = false;
              m.redraw();
            });
          }}
        >
          {t('settings.hide_signatures')}
          <span className="helpText">{t('settings.hide_signatures_help')}</span>
        </Switch>
      </FieldSet>,
      -10
    );
  });
});
