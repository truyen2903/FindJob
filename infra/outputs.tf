output "elastic_ip" {
  description = "Elastic IP of the EC2 instance"
  value       = aws_eip.web_ip.public_ip
}

output "instance_id" {
  description = "EC2 instance ID"
  value       = aws_instance.web.id
}

output "ssh_command" {
  description = "SSH command"
  value       = "ssh -i ~/.ssh/aws/findjob-key.pem ubuntu@${aws_eip.web_ip.public_ip}"
}

output "production_url" {
  description = "Production domain"
  value       = "http://${var.domain_name}"
}

output "staging_url" {
  description = "Staging domain"
  value       = "http://${var.staging_domain_name}"
}
