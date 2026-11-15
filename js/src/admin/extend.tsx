import Extend from 'flarum/common/extenders';
import app from 'flarum/admin/app';

const t = (key: string) => app.translator.trans(`ernestdefoe-nameplate.admin.${key}`);
const toggle = (key: string, help?: boolean) => () => ({
  setting: `ernestdefoe-nameplate.${key}`,
  type: 'boolean',
  label: t(key),
  ...(help ? { help: t(`${key}_help`) } : {}),
});
const heading = (key: string) => () => <h3 className="NameplateAdmin-heading">{t(key)}</h3>;

// Explicit priorities keep the page in this order.
export default [
  new Extend.Admin()
    .setting(
      () => ({
        setting: 'ernestdefoe-nameplate.layout',
        type: 'select',
        label: t('layout'),
        help: t('layout_help'),
        options: { side: t('layout_side'), header: t('layout_header') },
        default: 'side',
      }),
      110
    )
    .customSetting(heading('show_heading'), 100)
    .setting(toggle('show_rank', true), 90)
    .setting(toggle('show_renown', true), 80)
    .setting(toggle('show_posts'), 70)
    .setting(toggle('show_discussions'), 60)
    .setting(toggle('show_likes', true), 50)
    .setting(toggle('show_best_answers', true), 40)
    .setting(toggle('show_joined'), 30)
    .customSetting(heading('signatures_heading'), 20)
    .setting(toggle('signature_once', true), 10),
];
