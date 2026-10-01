<script setup>
import { computed, onBeforeUnmount, ref, watch } from 'vue'
import copy from 'clipboard-copy'
import { createWorkerPool } from '../utils/workerPool'
import { useToast } from '../composables/useToast'
import encryptWorkerUrl from '../workers/zy-encrypt.worker.js?worker&url'
import hashWorkerUrl from '../workers/zy-hash.worker.js?worker&url'
import encodeWorkerUrl from '../workers/zy-encode.worker.js?worker&url'

const toast = useToast()

const mainTabs = [
  { value: 'encrypt', label: '加解密' },
  { value: 'hash', label: 'Hash / HMAC' },
  { value: 'encode', label: '编码转换' }
]

const encryptAlgorithms = [
  { value: 'rsa', label: 'RSA' },
  { value: 'aes', label: 'AES' },
  { value: 'des', label: 'DES' },
  { value: 'tripleDes', label: 'Triple DES' },
  { value: 'rc4', label: 'RC4' },
  { value: 'rc4Drop', label: 'RC4-Drop' },
  { value: 'rabbit', label: 'Rabbit' },
  { value: 'rabbitLegacy', label: 'Rabbit Legacy' },
  { value: 'sm4', label: 'SM4' }
]

const hashAlgorithms = [
  { value: 'hash', label: 'Hash' },
  { value: 'hmac', label: 'HMAC' }
]

const encodeAlgorithms = [
  { value: 'html', label: 'HTML' },
  { value: 'unicode', label: 'Unicode' },
  { value: 'base64', label: 'Base64' },
  { value: 'url', label: 'URL' },
  { value: 'hex', label: 'Hex' },
  { value: 'gzip', label: 'Gzip' }
]

const hashNames = [
  { value: 'md5-16', label: 'MD5-16' },
  { value: 'md5-32', label: 'MD5-32' },
  { value: 'sha1', label: 'SHA1' },
  { value: 'sha224', label: 'SHA224' },
  { value: 'sha256', label: 'SHA256' },
  { value: 'sha3', label: 'SHA3' },
  { value: 'sha384', label: 'SHA384' },
  { value: 'sha512', label: 'SHA512' },
  { value: 'sha512-224', label: 'SHA512/224' },
  { value: 'sha512-256', label: 'SHA512/256' },
  { value: 'ripemd160', label: 'RIPEMD160' },
  { value: 'sm3', label: 'SM3' }
]

const byteEncodings = [
  { value: 'utf8', label: 'UTF-8' },
  { value: 'base64', label: 'Base64' },
  { value: 'hex', label: 'Hex' }
]

const textEncodings = [
  { value: 'utf8', label: 'UTF-8' },
  { value: 'base64', label: 'Base64' },
  { value: 'hex', label: 'Hex' },
  { value: 'latin1', label: 'Latin1' }
]

const hashOutputEncodings = [
  { value: 'hex', label: 'Hex' },
  { value: 'base64', label: 'Base64' }
]

const unicodePrefixes = [
  { value: '\\u', label: '\\u' },
  { value: '%u', label: '%u' },
  { value: 'U+', label: 'U+' }
]

const htmlEntitiesOptions = [
  { value: 'named', label: 'Named' },
  { value: 'hex', label: 'Hex' },
  { value: 'numeric', label: 'Numeric' }
]

const urlTypeOptions = [
  { value: 'component', label: 'Component' },
  { value: 'uri', label: 'URI' }
]

const hexDelimiterOptions = [
  { value: 'none', label: 'None' },
  { value: 'space', label: 'Space' },
  { value: 'percent', label: 'Percent' },
  { value: 'comma', label: 'Comma' },
  { value: 'semiColon', label: 'Semicolon' },
  { value: 'colon', label: 'Colon' },
  { value: 'lineFeed', label: 'LF' },
  { value: 'crlf', label: 'CRLF' },
  { value: '0x', label: '0x' },
  { value: '0xComma', label: '0x,' },
  { value: '\\x', label: '\\x' }
]

const mainTab = ref('encrypt')
const subTabs = ref({
  encrypt: 'rsa',
  hash: 'hash',
  encode: 'html'
})

const encryptForm = ref({
  input: '',
  key: '',
  keyEncode: 'utf8',
  passphrase: '',
  passphraseEncode: 'utf8',
  iv: '',
  ivEncode: 'utf8',
  mode: '0',
  long: '0',
  pad: 'rsa-oaep',
  drop: 192,
  tag: '',
  tagEncode: 'hex',
  aad: '',
  aadEncode: 'utf8',
  inputEncode: 'utf8',
  outputEncode: 'base64',
  output: ''
})

const hashForm = ref({
  input: '',
  key: '',
  inputEncode: 'utf8',
  keyEncode: 'utf8',
  outputEncode: 'hex',
  output: {}
})

const encodeForm = ref({
  input: '',
  output: '',
  unicodePrefix: '\\u',
  unicodePad: 4,
  unicodeEncodeEverything: true,
  htmlEntities: 'named',
  htmlEncodeEverything: true,
  urlType: 'component',
  hexDelimiter: 'none',
  inputEncode: 'utf8',
  outputEncode: 'utf8'
})

const loading = ref({ encrypt: '', hash: false, encode: '' })
const error = ref('')

const encryptPool = createWorkerPool(encryptWorkerUrl)
const hashPool = createWorkerPool(hashWorkerUrl)
const encodePool = createWorkerPool(encodeWorkerUrl)

const currentEncryptAlgorithm = computed(() => subTabs.value.encrypt)
const currentHashAlgorithm = computed(() => subTabs.value.hash)
const currentEncodeAlgorithm = computed(() => subTabs.value.encode)
const encodeAction = ref('encode')

const encodeInputLabel = computed(() => encodeAction.value === 'encode' ? '源文本编码' : '输入数据编码')
const encodeOutputLabel = computed(() => encodeAction.value === 'encode' ? '结果编码' : '输出文本编码')
const encodeHint = computed(() => {
  switch (currentEncodeAlgorithm.value) {
    case 'gzip':
      return encodeAction.value === 'encode'
        ? '推荐：编码时使用 UTF-8 → Base64。压缩结果建议保存为 Base64。'
        : '推荐：解码时使用 Base64 → UTF-8。请将待解压内容作为 Base64 输入。'
    case 'base64':
      return encodeAction.value === 'encode'
        ? '推荐：普通文本使用 UTF-8 输入，结果按 UTF-8 或 Latin1 查看。'
        : '推荐：将 Base64 结果作为输入，输出通常选择 UTF-8。'
    case 'hex':
      return encodeAction.value === 'encode'
        ? '推荐：普通文本使用 UTF-8 输入，按需要选择 Hex 输出。'
        : '推荐：将 Hex 字符串作为输入，输出通常选择 UTF-8。'
    case 'unicode':
      return 'Unicode 常用于转义展示，通常输入与输出都使用 UTF-8。'
    case 'html':
      return 'HTML 实体转换通常输入与输出都使用 UTF-8。'
    case 'url':
      return 'URL 编解码通常输入与输出都使用 UTF-8。'
    default:
      return ''
  }
})

const getEncryptModeOptions = (algorithm) => {
  switch (algorithm) {
    case 'rsa':
      return [
        { value: '0', label: '公钥加密 / 私钥解密' },
        { value: '1', label: '私钥加密 / 公钥解密' }
      ]
    case 'sm4':
      return [
        { value: 'cbc', label: 'CBC' },
        { value: 'ecb', label: 'ECB' },
        { value: 'gcm', label: 'GCM' }
      ]
    case 'aes':
      return [
        { value: 'cbc', label: 'CBC' },
        { value: 'cfb', label: 'CFB' },
        { value: 'ofb', label: 'OFB' },
        { value: 'ctr', label: 'CTR' },
        { value: 'ecb', label: 'ECB' },
        { value: 'gcm', label: 'GCM' }
      ]
    default:
      return [
        { value: 'cbc', label: 'CBC' },
        { value: 'cfb', label: 'CFB' },
        { value: 'ofb', label: 'OFB' },
        { value: 'ctr', label: 'CTR' },
        { value: 'ecb', label: 'ECB' }
      ]
  }
}

const getEncryptPadOptions = (algorithm) => {
  switch (algorithm) {
    case 'rsa':
      return [
        { value: 'rsa-oaep', label: 'RSA-OAEP' },
        { value: 'rsaes-pkcs1-v1_5', label: 'RSAES-PKCS1' }
      ]
    case 'sm4':
      return [
        { value: 'pkcs7padding', label: 'PKCS7' },
        { value: 'nopadding', label: 'No Padding' }
      ]
    default:
      return [
        { value: 'pkcs7padding', label: 'PKCS7' },
        { value: 'ansix923', label: 'AnsiX923' },
        { value: 'iso10126', label: 'Iso10126' },
        { value: 'iso97971', label: 'Iso97971' },
        { value: 'zeropadding', label: 'Zero Padding' },
        { value: 'nopadding', label: 'No Padding' }
      ]
  }
}

const isRsa = computed(() => currentEncryptAlgorithm.value === 'rsa')
const showIv = computed(() => !['rsa', 'rc4', 'rc4Drop'].includes(currentEncryptAlgorithm.value))
const showMode = computed(() => !['rc4', 'rc4Drop', 'rabbit', 'rabbitLegacy'].includes(currentEncryptAlgorithm.value))
const showLong = computed(() => currentEncryptAlgorithm.value === 'rsa' && encryptForm.value.mode === '0')
const showPad = computed(() => {
  if (!showMode.value) return false
  if (currentEncryptAlgorithm.value === 'rsa') return ['0', '1'].includes(encryptForm.value.mode)
  return ['aes', 'des', 'tripleDes', 'sm4'].includes(currentEncryptAlgorithm.value) && ['cbc', 'ecb'].includes(encryptForm.value.mode)
})
const showDrop = computed(() => currentEncryptAlgorithm.value === 'rc4Drop')
const showTagAad = computed(() => ['aes', 'sm4'].includes(currentEncryptAlgorithm.value) && encryptForm.value.mode === 'gcm')

watch(currentEncryptAlgorithm, (algorithm) => {
  const modeOptions = getEncryptModeOptions(algorithm)
  if (!modeOptions.some(item => item.value === encryptForm.value.mode)) {
    encryptForm.value.mode = modeOptions[0].value
  }
  const padOptions = getEncryptPadOptions(algorithm)
  if (!padOptions.some(item => item.value === encryptForm.value.pad)) {
    encryptForm.value.pad = padOptions[0].value
  }
  encryptForm.value.output = ''
  error.value = ''
})

watch(currentEncodeAlgorithm, (algorithm) => {
  encodeAction.value = 'encode'

  if (algorithm === 'gzip') {
    encodeForm.value.inputEncode = 'utf8'
    encodeForm.value.outputEncode = 'base64'
  } else if (algorithm === 'base64') {
    encodeForm.value.inputEncode = 'utf8'
    encodeForm.value.outputEncode = 'utf8'
  } else if (algorithm === 'hex') {
    encodeForm.value.inputEncode = 'utf8'
    encodeForm.value.outputEncode = 'utf8'
  } else if (algorithm === 'html') {
    encodeForm.value.inputEncode = 'utf8'
    encodeForm.value.outputEncode = 'utf8'
  } else if (algorithm === 'unicode') {
    encodeForm.value.inputEncode = 'utf8'
    encodeForm.value.outputEncode = 'utf8'
  } else if (algorithm === 'url') {
    encodeForm.value.inputEncode = 'utf8'
    encodeForm.value.outputEncode = 'utf8'
  }

  encodeForm.value.output = ''
  error.value = ''
}, { immediate: true })

const setMainTab = (value) => {
  mainTab.value = value
  error.value = ''
}

const setSubTab = (group, value) => {
  subTabs.value[group] = value
  error.value = ''
}

const clearEncryptInput = () => {
  encryptForm.value.input = ''
}

const clearEncryptOutput = () => {
  encryptForm.value.output = ''
}

const clearEncodeInput = () => {
  encodeForm.value.input = ''
}

const clearEncodeOutput = () => {
  encodeForm.value.output = ''
}

const clearHashInput = () => {
  hashForm.value.input = ''
  hashForm.value.output = {}
}

const copyText = async (text) => {
  if (!text) return
  try {
    await copy(text)
    toast.success('已复制到剪贴板')
  } catch (e) {
    toast.error('复制失败: ' + e.message)
  }
}

const buildEncryptParams = (action) => {
  if (encryptForm.value.outputEncode === 'utf8' && action === 'encode') {
    throw new Error('加密输出不支持 UTF-8，请改用 Base64 或 Hex')
  }
  if (encryptForm.value.inputEncode === 'utf8' && action === 'decode') {
    throw new Error('解密输入不支持 UTF-8，请改用 Base64 或 Hex')
  }

  let params = {
    src: encryptForm.value.input,
    inputEncode: encryptForm.value.inputEncode,
    outputEncode: encryptForm.value.outputEncode
  }

  if (isRsa.value) {
    params = {
      ...params,
      key: encryptForm.value.key,
      pad: encryptForm.value.pad,
      passphrase: encryptForm.value.passphrase,
      passphraseEncode: encryptForm.value.passphraseEncode,
      type: Number(encryptForm.value.mode),
      long: encryptForm.value.long === '1'
    }
  } else if (currentEncryptAlgorithm.value === 'rc4') {
    params = {
      ...params,
      key: encryptForm.value.key,
      keyEncode: encryptForm.value.keyEncode
    }
  } else if (currentEncryptAlgorithm.value === 'rc4Drop') {
    params = {
      ...params,
      key: encryptForm.value.key,
      keyEncode: encryptForm.value.keyEncode,
      drop: Number(encryptForm.value.drop)
    }
  } else if (['aes', 'sm4'].includes(currentEncryptAlgorithm.value)) {
    params = {
      ...params,
      key: encryptForm.value.key,
      keyEncode: encryptForm.value.keyEncode,
      iv: encryptForm.value.iv,
      ivEncode: encryptForm.value.ivEncode,
      mode: encryptForm.value.mode,
      pad: encryptForm.value.pad,
      tag: encryptForm.value.tag,
      tagEncode: encryptForm.value.tagEncode,
      aad: encryptForm.value.aad,
      aadEncode: encryptForm.value.aadEncode
    }
  } else if (['rabbit', 'rabbitLegacy'].includes(currentEncryptAlgorithm.value)) {
    params = {
      ...params,
      key: encryptForm.value.key,
      keyEncode: encryptForm.value.keyEncode,
      iv: encryptForm.value.iv,
      ivEncode: encryptForm.value.ivEncode
    }
  } else {
    params = {
      ...params,
      key: encryptForm.value.key,
      keyEncode: encryptForm.value.keyEncode,
      iv: encryptForm.value.iv,
      ivEncode: encryptForm.value.ivEncode,
      mode: encryptForm.value.mode,
      pad: encryptForm.value.pad
    }
  }

  return params
}

const executeEncrypt = async (action) => {
  if (!encryptForm.value.input || loading.value.encrypt) return
  loading.value.encrypt = action
  error.value = ''
  try {
    const result = await encryptPool.exec('main', [currentEncryptAlgorithm.value, action, buildEncryptParams(action)])
    encryptForm.value.output = result
  } catch (e) {
    encryptForm.value.output = ''
    error.value = e.message
  } finally {
    loading.value.encrypt = ''
  }
}

const executeEncode = async (action) => {
  if (!encodeForm.value.input || loading.value.encode) return
  loading.value.encode = action
  encodeAction.value = action
  error.value = ''
  try {
    const algorithm = currentEncodeAlgorithm.value
    const params = {
      src: encodeForm.value.input,
      inputEncode: action === 'encode' ? 'utf8' : encodeForm.value.inputEncode,
      outputEncode: action === 'encode' ? encodeForm.value.outputEncode : 'utf8',
      prefix: encodeForm.value.unicodePrefix,
      pad: Number(encodeForm.value.unicodePad),
      encodeEverything: algorithm === 'unicode' ? encodeForm.value.unicodeEncodeEverything : encodeForm.value.htmlEncodeEverything,
      entities: encodeForm.value.htmlEntities,
      type: encodeForm.value.urlType,
      delimiter: encodeForm.value.hexDelimiter
    }
    const result = await encodePool.exec('main', [algorithm, action, params])
    encodeForm.value.output = result
  } catch (e) {
    encodeForm.value.output = ''
    error.value = e.message
  } finally {
    loading.value.encode = ''
  }
}

const executeHash = async () => {
  if (!hashForm.value.input || loading.value.hash) return
  loading.value.hash = true
  error.value = ''
  try {
    const entries = await Promise.all(
      hashNames.map(async (item) => {
        const params = currentHashAlgorithm.value === 'hash'
          ? {
              src: hashForm.value.input,
              inputEncode: hashForm.value.inputEncode,
              outputEncode: hashForm.value.outputEncode
            }
          : {
              src: hashForm.value.input,
              key: hashForm.value.key,
              inputEncode: hashForm.value.inputEncode,
              keyEncode: hashForm.value.keyEncode,
              outputEncode: hashForm.value.outputEncode
            }
        const result = await hashPool.exec('main', [currentHashAlgorithm.value, item.value, params])
        return [item.value, result]
      })
    )
    hashForm.value.output = Object.fromEntries(entries)
  } catch (e) {
    hashForm.value.output = {}
    error.value = e.message
  } finally {
    loading.value.hash = false
  }
}

onBeforeUnmount(async () => {
  await Promise.all([
    encryptPool.terminate(),
    hashPool.terminate(),
    encodePool.terminate()
  ])
})
</script>

<template>
  <div class="zy-crypto-page">
    <div class="zy-crypto-header">
      <PageHeader eyebrow="// ZY CRYPTO" title="ZY 加解密" desc="基于 ZyPlayer crypto example 的纯前端加解密、Hash 与编码转换工具">
        <template #actions>
          <div class="flex rounded-ctrl overflow-hidden border border-line text-xs">
            <button
              v-for="(tab, i) in mainTabs" :key="tab.value"
              class="px-3 h-9 transition-colors"
              :class="[
                i > 0 ? 'border-l border-line' : '',
                mainTab === tab.value ? 'bg-accent-dim text-accent' : 'text-mid hover:text-hi hover:bg-mid/5'
              ]"
              @click="setMainTab(tab.value)"
            >{{ tab.label }}</button>
          </div>
        </template>
      </PageHeader>

      <div class="card p-3 mt-3 space-y-2.5">
        <div class="flex gap-1.5 overflow-x-auto pb-1 hide-scrollbar">
          <button
            v-for="item in mainTab === 'encrypt' ? encryptAlgorithms : (mainTab === 'hash' ? hashAlgorithms : encodeAlgorithms)"
            :key="item.value"
            @click="setSubTab(mainTab, item.value)"
            class="px-3 h-8 rounded-full text-xs whitespace-nowrap transition-colors border shrink-0"
            :class="subTabs[mainTab] === item.value
              ? 'bg-accent-dim text-accent border-accent/40'
              : 'text-mid border-line hover:text-hi hover:border-mid/40'"
          >
            {{ item.label }}
          </button>
        </div>
      </div>
    </div>

    <div class="zy-crypto-content">
      <div v-if="error" class="card p-4 mb-4 border-red-200 dark:border-red-800 bg-red-50 dark:bg-red-900/20 text-red-600 dark:text-red-300 text-sm whitespace-pre-wrap">
        {{ error }}
      </div>

      <div v-if="mainTab === 'encrypt'" class="grid grid-cols-1 xl:grid-cols-2 gap-4">
        <div class="card p-4 space-y-4">
          <div class="flex items-center justify-between">
            <h3 class="font-semibold">输入参数</h3>
            <button v-if="encryptForm.input" @click="clearEncryptInput" class="text-sm text-gray-500 hover:text-red-500">清空输入</button>
          </div>

          <div class="space-y-2">
            <label class="text-sm font-medium">内容</label>
            <textarea v-model="encryptForm.input" class="input min-h-[120px] font-mono" spellcheck="false" />
          </div>

          <div class="space-y-2">
            <label class="text-sm font-medium">密钥</label>
            <textarea v-if="isRsa" v-model="encryptForm.key" class="input min-h-[120px] font-mono" spellcheck="false" />
            <div v-else class="grid grid-cols-1 sm:grid-cols-[140px_1fr] gap-2">
              <select v-model="encryptForm.keyEncode" class="input">
                <option v-for="item in byteEncodings" :key="item.value" :value="item.value">{{ item.label }}</option>
              </select>
              <input v-model="encryptForm.key" class="input" />
            </div>
          </div>

          <div v-if="isRsa" class="space-y-2">
            <label class="text-sm font-medium">私钥口令</label>
            <div class="grid grid-cols-1 sm:grid-cols-[140px_1fr] gap-2">
              <select v-model="encryptForm.passphraseEncode" class="input">
                <option v-for="item in byteEncodings" :key="item.value" :value="item.value">{{ item.label }}</option>
              </select>
              <input v-model="encryptForm.passphrase" class="input" />
            </div>
          </div>

          <div v-if="showIv" class="space-y-2">
            <label class="text-sm font-medium">IV</label>
            <div class="grid grid-cols-1 sm:grid-cols-[140px_1fr] gap-2">
              <select v-model="encryptForm.ivEncode" class="input">
                <option v-for="item in byteEncodings" :key="item.value" :value="item.value">{{ item.label }}</option>
              </select>
              <input v-model="encryptForm.iv" class="input" />
            </div>
          </div>

          <div v-if="showMode" class="space-y-2">
            <label class="text-sm font-medium">模式</label>
            <div class="flex flex-wrap gap-2">
              <button
                v-for="item in getEncryptModeOptions(currentEncryptAlgorithm)"
                :key="item.value"
                @click="encryptForm.mode = item.value"
                class="px-3 py-1.5 rounded-lg text-sm transition-colors"
                :class="encryptForm.mode === item.value
                  ? 'bg-primary-600 text-white'
                  : 'bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300'"
              >
                {{ item.label }}
              </button>
            </div>
          </div>

          <div v-if="showLong" class="space-y-2">
            <label class="text-sm font-medium">长文本</label>
            <div class="flex gap-2">
              <button @click="encryptForm.long = '0'" class="px-3 py-1.5 rounded-lg text-sm" :class="encryptForm.long === '0' ? 'bg-primary-600 text-white' : 'bg-gray-100 dark:bg-gray-700'">标准</button>
              <button @click="encryptForm.long = '1'" class="px-3 py-1.5 rounded-lg text-sm" :class="encryptForm.long === '1' ? 'bg-primary-600 text-white' : 'bg-gray-100 dark:bg-gray-700'">长文本</button>
            </div>
          </div>

          <div v-if="showPad" class="space-y-2">
            <label class="text-sm font-medium">填充</label>
            <div class="flex flex-wrap gap-2">
              <button
                v-for="item in getEncryptPadOptions(currentEncryptAlgorithm)"
                :key="item.value"
                @click="encryptForm.pad = item.value"
                class="px-3 py-1.5 rounded-lg text-sm transition-colors"
                :class="encryptForm.pad === item.value
                  ? 'bg-primary-600 text-white'
                  : 'bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300'"
              >
                {{ item.label }}
              </button>
            </div>
          </div>

          <div v-if="showDrop" class="space-y-2">
            <label class="text-sm font-medium">Drop</label>
            <input v-model.number="encryptForm.drop" type="number" min="0" class="input w-32" />
          </div>

          <template v-if="showTagAad">
            <div class="space-y-2">
              <label class="text-sm font-medium">Tag</label>
              <div class="grid grid-cols-1 sm:grid-cols-[140px_1fr] gap-2">
                <select v-model="encryptForm.tagEncode" class="input">
                  <option v-for="item in byteEncodings" :key="item.value" :value="item.value">{{ item.label }}</option>
                </select>
                <input v-model="encryptForm.tag" class="input" />
              </div>
            </div>
            <div class="space-y-2">
              <label class="text-sm font-medium">AAD</label>
              <div class="grid grid-cols-1 sm:grid-cols-[140px_1fr] gap-2">
                <select v-model="encryptForm.aadEncode" class="input">
                  <option v-for="item in byteEncodings" :key="item.value" :value="item.value">{{ item.label }}</option>
                </select>
                <input v-model="encryptForm.aad" class="input" />
              </div>
            </div>
          </template>

          <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
            <div class="space-y-2">
              <label class="text-sm font-medium">输入编码</label>
              <div class="flex gap-2 flex-wrap">
                <button
                  v-for="item in byteEncodings"
                  :key="item.value"
                  @click="encryptForm.inputEncode = item.value"
                  class="px-3 py-1.5 rounded-lg text-sm"
                  :class="encryptForm.inputEncode === item.value ? 'bg-primary-600 text-white' : 'bg-gray-100 dark:bg-gray-700'"
                >
                  {{ item.label }}
                </button>
              </div>
            </div>
            <div class="space-y-2">
              <label class="text-sm font-medium">输出编码</label>
              <div class="flex gap-2 flex-wrap">
                <button
                  v-for="item in byteEncodings"
                  :key="item.value"
                  @click="encryptForm.outputEncode = item.value"
                  class="px-3 py-1.5 rounded-lg text-sm"
                  :class="encryptForm.outputEncode === item.value ? 'bg-primary-600 text-white' : 'bg-gray-100 dark:bg-gray-700'"
                >
                  {{ item.label }}
                </button>
              </div>
            </div>
          </div>

          <div class="grid grid-cols-2 gap-2">
            <button @click="executeEncrypt('encode')" :disabled="!!loading.encrypt" class="btn btn-primary">
              {{ loading.encrypt === 'encode' ? '处理中...' : '加密' }}
            </button>
            <button @click="executeEncrypt('decode')" :disabled="!!loading.encrypt" class="btn btn-secondary">
              {{ loading.encrypt === 'decode' ? '处理中...' : '解密' }}
            </button>
          </div>
        </div>

        <div class="card p-4 flex flex-col min-h-[520px]">
          <div class="flex items-center justify-between mb-4">
            <h3 class="font-semibold">输出结果</h3>
            <div class="flex gap-2">
              <button v-if="encryptForm.output" @click="clearEncryptOutput" class="text-sm text-gray-500 hover:text-red-500">清空</button>
              <button @click="copyText(encryptForm.output)" :disabled="!encryptForm.output" class="btn btn-secondary text-sm">复制</button>
            </div>
          </div>
          <textarea v-model="encryptForm.output" readonly class="input flex-1 min-h-[420px] font-mono" spellcheck="false" />
        </div>
      </div>

      <div v-else-if="mainTab === 'hash'" class="grid grid-cols-1 xl:grid-cols-[1.2fr_1fr] gap-4">
        <div class="card p-4 space-y-4">
          <div class="flex items-center justify-between">
            <h3 class="font-semibold">Hash 输入</h3>
            <button v-if="hashForm.input" @click="clearHashInput" class="text-sm text-gray-500 hover:text-red-500">清空</button>
          </div>

          <div class="space-y-2">
            <label class="text-sm font-medium">内容</label>
            <textarea v-model="hashForm.input" class="input min-h-[120px] font-mono" spellcheck="false" />
          </div>

          <div v-if="currentHashAlgorithm === 'hmac'" class="space-y-2">
            <label class="text-sm font-medium">密钥</label>
            <div class="grid grid-cols-1 sm:grid-cols-[140px_1fr] gap-2">
              <select v-model="hashForm.keyEncode" class="input">
                <option v-for="item in byteEncodings" :key="item.value" :value="item.value">{{ item.label }}</option>
              </select>
              <input v-model="hashForm.key" class="input" />
            </div>
          </div>

          <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
            <div class="space-y-2">
              <label class="text-sm font-medium">输入编码</label>
              <div class="flex gap-2 flex-wrap">
                <button
                  v-for="item in byteEncodings"
                  :key="item.value"
                  @click="hashForm.inputEncode = item.value"
                  class="px-3 py-1.5 rounded-lg text-sm"
                  :class="hashForm.inputEncode === item.value ? 'bg-primary-600 text-white' : 'bg-gray-100 dark:bg-gray-700'"
                >
                  {{ item.label }}
                </button>
              </div>
            </div>
            <div class="space-y-2">
              <label class="text-sm font-medium">输出编码</label>
              <div class="flex gap-2 flex-wrap">
                <button
                  v-for="item in hashOutputEncodings"
                  :key="item.value"
                  @click="hashForm.outputEncode = item.value"
                  class="px-3 py-1.5 rounded-lg text-sm"
                  :class="hashForm.outputEncode === item.value ? 'bg-primary-600 text-white' : 'bg-gray-100 dark:bg-gray-700'"
                >
                  {{ item.label }}
                </button>
              </div>
            </div>
          </div>

          <button @click="executeHash" :disabled="loading.hash" class="btn btn-primary w-full">
            {{ loading.hash ? '计算中...' : '计算全部算法' }}
          </button>
        </div>

        <div class="card p-4 space-y-3">
          <div class="flex items-center justify-between">
            <h3 class="font-semibold">输出结果</h3>
          </div>
          <div class="space-y-2 max-h-[640px] overflow-y-auto pr-1">
            <div v-for="item in hashNames" :key="item.value" class="grid grid-cols-[110px_1fr_auto] gap-2 items-center">
              <span class="text-sm text-gray-500 dark:text-gray-400">{{ item.label }}</span>
              <input :value="hashForm.output[item.value] || ''" readonly class="input font-mono text-xs" />
              <button @click="copyText(hashForm.output[item.value])" :disabled="!hashForm.output[item.value]" class="btn btn-secondary text-xs px-3">复制</button>
            </div>
          </div>
        </div>
      </div>

      <div v-else class="grid grid-cols-1 xl:grid-cols-2 gap-4">
        <div class="card p-4 space-y-4">
          <div class="flex items-center justify-between">
            <h3 class="font-semibold">输入参数</h3>
            <button v-if="encodeForm.input" @click="clearEncodeInput" class="text-sm text-gray-500 hover:text-red-500">清空输入</button>
          </div>

          <div class="space-y-2">
            <label class="text-sm font-medium">内容</label>
            <textarea v-model="encodeForm.input" class="input min-h-[140px] font-mono" spellcheck="false" />
          </div>

          <div v-if="encodeHint" class="rounded-lg border border-blue-200 bg-blue-50 px-3 py-2 text-sm text-blue-700 dark:border-blue-800 dark:bg-blue-900/20 dark:text-blue-300">
            {{ encodeHint }}
          </div>

          <template v-if="currentEncodeAlgorithm === 'unicode'">
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
              <div class="space-y-2">
                <label class="text-sm font-medium">前缀</label>
                <select v-model="encodeForm.unicodePrefix" class="input">
                  <option v-for="item in unicodePrefixes" :key="item.value" :value="item.value">{{ item.label }}</option>
                </select>
              </div>
              <div class="space-y-2">
                <label class="text-sm font-medium">补位长度</label>
                <input v-model.number="encodeForm.unicodePad" type="number" min="1" class="input" />
              </div>
            </div>
            <label class="flex items-center gap-2 text-sm">
              <input v-model="encodeForm.unicodeEncodeEverything" type="checkbox" class="rounded border-gray-300 dark:border-gray-600 text-primary-600 focus:ring-primary-500" />
              编码全部字符
            </label>
          </template>

          <template v-if="currentEncodeAlgorithm === 'html'">
            <div class="space-y-2">
              <label class="text-sm font-medium">实体格式</label>
              <select v-model="encodeForm.htmlEntities" class="input">
                <option v-for="item in htmlEntitiesOptions" :key="item.value" :value="item.value">{{ item.label }}</option>
              </select>
            </div>
            <label class="flex items-center gap-2 text-sm">
              <input v-model="encodeForm.htmlEncodeEverything" type="checkbox" class="rounded border-gray-300 dark:border-gray-600 text-primary-600 focus:ring-primary-500" />
              编码全部字符
            </label>
          </template>

          <div v-if="currentEncodeAlgorithm === 'url'" class="space-y-2">
            <label class="text-sm font-medium">URL 类型</label>
            <select v-model="encodeForm.urlType" class="input">
              <option v-for="item in urlTypeOptions" :key="item.value" :value="item.value">{{ item.label }}</option>
            </select>
          </div>

          <div v-if="currentEncodeAlgorithm === 'hex'" class="space-y-2">
            <label class="text-sm font-medium">分隔符</label>
            <select v-model="encodeForm.hexDelimiter" class="input">
              <option v-for="item in hexDelimiterOptions" :key="item.value" :value="item.value">{{ item.label }}</option>
            </select>
          </div>

          <div v-if="['base64', 'gzip', 'hex', 'html', 'unicode', 'url'].includes(currentEncodeAlgorithm)" class="grid grid-cols-1 lg:grid-cols-2 gap-4">
            <div class="space-y-2">
              <label class="text-sm font-medium">{{ encodeInputLabel }}</label>
              <select v-model="encodeForm.inputEncode" class="input">
                <option v-for="item in textEncodings" :key="item.value" :value="item.value">{{ item.label }}</option>
              </select>
            </div>
            <div class="space-y-2">
              <label class="text-sm font-medium">{{ encodeOutputLabel }}</label>
              <select v-model="encodeForm.outputEncode" class="input">
                <option v-for="item in textEncodings" :key="item.value" :value="item.value">{{ item.label }}</option>
              </select>
            </div>
          </div>

          <div class="grid grid-cols-2 gap-2">
            <button @click="executeEncode('encode')" :disabled="!!loading.encode" class="btn btn-primary">
              {{ loading.encode === 'encode' ? '处理中...' : '编码' }}
            </button>
            <button @click="executeEncode('decode')" :disabled="!!loading.encode" class="btn btn-secondary">
              {{ loading.encode === 'decode' ? '处理中...' : '解码' }}
            </button>
          </div>
        </div>

        <div class="card p-4 flex flex-col min-h-[520px]">
          <div class="flex items-center justify-between mb-4">
            <h3 class="font-semibold">输出结果</h3>
            <div class="flex gap-2">
              <button v-if="encodeForm.output" @click="clearEncodeOutput" class="text-sm text-gray-500 hover:text-red-500">清空</button>
              <button @click="copyText(encodeForm.output)" :disabled="!encodeForm.output" class="btn btn-secondary text-sm">复制</button>
            </div>
          </div>
          <textarea v-model="encodeForm.output" readonly class="input flex-1 min-h-[420px] font-mono" spellcheck="false" />
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
.hide-scrollbar {
  -ms-overflow-style: none;
  scrollbar-width: none;
}
.hide-scrollbar::-webkit-scrollbar {
  display: none;
}

.zy-crypto-page {
  display: flex;
  flex-direction: column;
  height: calc(100vh - 8rem - 4rem);
  min-height: 500px;
}

.zy-crypto-header {
  flex-shrink: 0;
  padding-bottom: 1rem;
}

.zy-crypto-content {
  flex: 1;
  overflow-y: auto;
  min-height: 0;
}
</style>
