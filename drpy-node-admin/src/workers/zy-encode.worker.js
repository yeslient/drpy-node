import { base64, gzip, hex, html, unicode, url } from '@zy/crypto'
import workerpool from 'workerpool'

const METHOD_MAP = { base64, gzip, hex, html, unicode, url }

const main = (action, execute, doc) => {
  const method = METHOD_MAP[action]?.[execute]
  return method(doc)
}

workerpool.worker({ main })
