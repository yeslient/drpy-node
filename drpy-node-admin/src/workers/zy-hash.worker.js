import { hash, hmac } from '@zy/crypto'
import workerpool from 'workerpool'

const METHOD_MAP = { hash, hmac }

const main = (action, name, doc) => {
  const method = METHOD_MAP[action]?.[name]
  return method(doc)
}

workerpool.worker({ main })
