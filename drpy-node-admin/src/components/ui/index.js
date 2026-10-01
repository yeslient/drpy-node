// ui 组件库统一安装：app.use(UI) 全局注册，页面模板直接使用 <UButton /> 等
import UButton from './UButton.vue'
import UInput from './UInput.vue'
import USelect from './USelect.vue'
import USwitch from './USwitch.vue'
import UBadge from './UBadge.vue'
import StatusDot from './StatusDot.vue'
import UTabs from './UTabs.vue'
import USkeleton from './USkeleton.vue'
import UEmpty from './UEmpty.vue'
import PageHeader from './PageHeader.vue'
import StatCard from './StatCard.vue'
import DataList from './DataList.vue'
import AppDrawer from './AppDrawer.vue'
import AppToast from './AppToast.vue'
import AppDialog from './AppDialog.vue'

export default {
  install(app) {
    for (const c of [UButton, UInput, USelect, USwitch, UBadge, StatusDot, UTabs, USkeleton, UEmpty, PageHeader, StatCard, DataList, AppDrawer, AppToast, AppDialog]) {
      app.component(c.name || c.__name, c)
    }
  },
}

export {
  UButton, UInput, USelect, USwitch, UBadge, StatusDot, UTabs, USkeleton, UEmpty,
  PageHeader, StatCard, DataList, AppDrawer, AppToast, AppDialog,
}
