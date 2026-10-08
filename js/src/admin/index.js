import app from 'flarum/admin/app';

const t = (key) => app.translator.trans(`ernestdefoe-nameplate.admin.${key}`);

app.initializers.add('ernestdefoe-nameplate', () => {
  app.registry
    .for('ernestdefoe-nameplate')
    .registerSetting({
      setting: 'ernestdefoe-nameplate.layout',
      type: 'select',
      label: t('layout'),
      help: t('layout_help'),
      options: { side: t('layout_side'), header: t('layout_header') },
      default: 'side',
    })
    .registerSetting(() => <h3 className="NameplateAdmin-heading">{t('show_heading')}</h3>)
    .registerSetting({ setting: 'ernestdefoe-nameplate.show_rank', type: 'boolean', label: t('show_rank'), help: t('show_rank_help') })
    .registerSetting({ setting: 'ernestdefoe-nameplate.show_posts', type: 'boolean', label: t('show_posts') })
    .registerSetting({ setting: 'ernestdefoe-nameplate.show_discussions', type: 'boolean', label: t('show_discussions') })
    .registerSetting({ setting: 'ernestdefoe-nameplate.show_likes', type: 'boolean', label: t('show_likes'), help: t('show_likes_help') })
    .registerSetting({
      setting: 'ernestdefoe-nameplate.show_best_answers',
      type: 'boolean',
      label: t('show_best_answers'),
      help: t('show_best_answers_help'),
    })
    .registerSetting({ setting: 'ernestdefoe-nameplate.show_joined', type: 'boolean', label: t('show_joined') })
    .registerSetting(() => <h3 className="NameplateAdmin-heading">{t('signatures_heading')}</h3>)
    .registerSetting({
      setting: 'ernestdefoe-nameplate.signature_once',
      type: 'boolean',
      label: t('signature_once'),
      help: t('signature_once_help'),
    });
});
