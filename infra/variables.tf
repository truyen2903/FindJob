variable "region" {
  description = "AWS region"
  type        = string
  default     = "ap-southeast-1"
}

variable "project_name" {
  description = "Project name prefix"
  type        = string
  default     = "jobfind"
}

variable "instance_type" {
  description = "EC2 instance type"
  type        = string
  default     = "t3.small"
}

variable "public_key_path" {
  description = "Path to SSH public key"
  type        = string
}

variable "ssh_cidr" {
  description = "CIDR allowed to SSH. Use 0.0.0.0/0 if GitHub Actions hosted runner needs SSH access."
  type        = string
  default     = "0.0.0.0/0"
}

variable "domain_name" {
  description = "Production domain"
  type        = string
  default     = "thaytruyen.id.vn"
}

variable "staging_domain_name" {
  description = "Staging domain"
  type        = string
  default     = "staging.thaytruyen.id.vn"
}

variable "staging_basic_auth_line" {
  description = "htpasswd line for staging basic auth, example: username:$2y$..."
  type        = string
  sensitive   = true
}

variable "repo_url" {
  description = "Git repository URL"
  type        = string
  default     = "https://github.com/truyen2903/FindJob.git"
}

variable "staging_app_dir" {
  description = "Staging app directory"
  type        = string
  default     = "/var/www/jobfind-staging"
}

variable "production_app_dir" {
  description = "Production app directory"
  type        = string
  default     = "/var/www/jobfind-production"
}
